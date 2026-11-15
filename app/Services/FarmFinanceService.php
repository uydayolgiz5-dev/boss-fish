<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FinanceSection;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\PondClosure;
use App\Models\User;
use App\Support\FinanceCategories;
use App\Support\WarehouseCategories;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmFinanceService
{
    /**
     * @return array{
     *   farm: array,
     *   categories: array<string, float>,
     *   custom_sections: list<array>,
     *   total_expenses: float,
     *   total_sales: float,
     *   profit: float,
     *   pond_id: int|null,
     *   ponds: list<array>,
     *   by_pond: list<array>,
     *   feed_allocated: float,
     *   medicine_allocated: float,
     *   equipment_allocated: float
     * }
     */
    public function overview(Farm $farm, ?int $pondId = null): array
    {
        $ponds = $farm->ponds()
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'area_m2', 'closed_at']);

        $pondIds = $ponds->pluck('id');
        $financeSnapshots = $this->financeSnapshotsByPond($pondIds);
        $feedUsageByPond = $this->feedUsageByPond($pondIds);
        $medicineUsageByPond = $this->medicineUsageByPond($pondIds);
        $farmFeedKg = round((float) $feedUsageByPond->sum(), 3);
        $farmMedicineKg = round((float) $medicineUsageByPond->sum(), 3);
        $equipmentSplit = $this->equipmentAreaSplit($ponds);

        $allExpenses = Expense::query()
            ->where('farm_id', $farm->id)
            ->get(['id', 'pond_id', 'category', 'amount', 'quantity']);

        $salesByPond = FishOperation::query()
            ->select('pond_id', DB::raw('SUM(amount) as total'))
            ->whereIn('pond_id', $pondIds)
            ->where('type', 'sale')
            ->groupBy('pond_id')
            ->pluck('total', 'pond_id');

        $byPond = $ponds->map(function (Pond $pond) use (
            $allExpenses,
            $salesByPond,
            $feedUsageByPond,
            $farmFeedKg,
            $medicineUsageByPond,
            $farmMedicineKg,
            $equipmentSplit,
            $financeSnapshots,
            $ponds
        ) {
            if ($this->isPondClosed($pond) && isset($financeSnapshots[$pond->id])) {
                return $this->pondRowFromSnapshot($pond, $financeSnapshots[$pond->id]);
            }

            $direct = round((float) $allExpenses
                ->where('pond_id', $pond->id)
                ->reject(fn (Expense $e) => $this->isConsumableWarehouseCategory((string) $e->category))
                ->sum('amount'), 2);

            $feedShare = $this->allocatedFarmWarehouseAmount(
                $allExpenses,
                FinanceCategories::FEED,
                (float) ($feedUsageByPond[$pond->id] ?? 0),
                $farmFeedKg
            );
            $medicineShare = $this->allocatedFarmWarehouseAmount(
                $allExpenses,
                FinanceCategories::MEDICINE,
                (float) ($medicineUsageByPond[$pond->id] ?? 0),
                $farmMedicineKg
            );
            $equipmentShare = $this->allocatedFarmEquipmentAmountForPond(
                $allExpenses,
                $ponds,
                $pond
            );

            $expenses = round($direct + $feedShare + $medicineShare + $equipmentShare, 2);
            $sales = round((float) ($salesByPond[$pond->id] ?? 0), 2);

            return [
                'pond_id' => $pond->id,
                'name' => $pond->name,
                'status' => $pond->status,
                'total_expenses' => $expenses,
                'direct_expenses' => $direct,
                'feed_allocated' => $feedShare,
                'medicine_allocated' => $medicineShare,
                'equipment_allocated' => $equipmentShare,
                'total_sales' => $sales,
                'profit' => round($sales - $expenses, 2),
                'feed_used_kg' => round((float) ($feedUsageByPond[$pond->id] ?? 0), 3),
                'medicine_used_kg' => round((float) ($medicineUsageByPond[$pond->id] ?? 0), 3),
                'is_closed' => $this->isPondClosed($pond),
                'finance_locked' => false,
            ];
        })->values()->all();

        if ($pondId) {
            $pond = $ponds->firstWhere('id', $pondId);
            if (! $pond) {
                abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
            }

            $row = collect($byPond)->firstWhere('pond_id', $pondId);
            $snapshot = $financeSnapshots[$pondId] ?? null;
            if ($this->isPondClosed($pond) && is_array($snapshot)) {
                $categories = $snapshot['categories'] ?? [];
                $sections = $snapshot['custom_sections'] ?? $this->customSections($farm, $categories);

                return [
                    'farm' => $farm->only(['id', 'name', 'region']),
                    'categories' => $categories,
                    'custom_sections' => $sections,
                    'total_expenses' => (float) ($snapshot['total_expenses'] ?? 0),
                    'total_sales' => (float) ($snapshot['total_sales'] ?? 0),
                    'profit' => (float) ($snapshot['profit'] ?? 0),
                    'pond_id' => $pondId,
                    'ponds' => $ponds->map(fn (Pond $p) => $this->pondPayload($p))->values()->all(),
                    'by_pond' => $byPond,
                    'feed_allocated' => (float) ($snapshot['feed_allocated'] ?? 0),
                    'medicine_allocated' => (float) ($snapshot['medicine_allocated'] ?? 0),
                    'equipment_allocated' => (float) ($snapshot['equipment_allocated'] ?? 0),
                    'finance_locked' => true,
                    'finance_locked_at' => $snapshot['locked_at'] ?? optional($pond->closed_at)?->toIso8601String(),
                ];
            }

            $categories = $this->categoriesForPond(
                $allExpenses,
                $pondId,
                (float) ($feedUsageByPond[$pondId] ?? 0),
                $farmFeedKg,
                (float) ($medicineUsageByPond[$pondId] ?? 0),
                $farmMedicineKg,
                (float) ($equipmentSplit[$pondId] ?? 0),
                $ponds,
                $pond
            );
            $sections = $this->customSections($farm, $categories);

            return [
                'farm' => $farm->only(['id', 'name', 'region']),
                'categories' => $categories,
                'custom_sections' => $sections,
                'total_expenses' => (float) ($row['total_expenses'] ?? 0),
                'total_sales' => (float) ($row['total_sales'] ?? 0),
                'profit' => (float) ($row['profit'] ?? 0),
                'pond_id' => $pondId,
                'ponds' => $ponds->map(fn (Pond $p) => $this->pondPayload($p))->values()->all(),
                'by_pond' => $byPond,
                'feed_allocated' => (float) ($row['feed_allocated'] ?? 0),
                'medicine_allocated' => (float) ($row['medicine_allocated'] ?? 0),
                'equipment_allocated' => (float) ($row['equipment_allocated'] ?? 0),
                'finance_locked' => false,
            ];
        }

        $byCategory = $allExpenses
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        $categories = collect(FinanceCategories::EXPENSE)->mapWithKeys(
            fn (string $category) => [$category => (float) ($byCategory[$category] ?? 0)]
        )->all();

        // Custom categories present in expenses but not in fixed list
        foreach ($byCategory as $category => $total) {
            if (! array_key_exists($category, $categories)) {
                $categories[$category] = (float) $total;
            }
        }

        $sections = $this->customSections($farm, $categories);
        $totalExpenses = round((float) $allExpenses->sum('amount'), 2);
        $totalSales = round((float) $salesByPond->sum(), 2);

        return [
            'farm' => $farm->only(['id', 'name', 'region']),
            'categories' => $categories,
            'custom_sections' => $sections,
            'total_expenses' => $totalExpenses,
            'total_sales' => $totalSales,
            'profit' => round($totalSales - $totalExpenses, 2),
            'pond_id' => null,
            'ponds' => $ponds->map(fn (Pond $p) => $this->pondPayload($p))->values()->all(),
            'by_pond' => $byPond,
            'feed_allocated' => 0.0,
            'medicine_allocated' => 0.0,
            'equipment_allocated' => 0.0,
            'finance_locked' => false,
        ];
    }

    public function capturePondSnapshot(Farm $farm, int $pondId): array
    {
        $overview = $this->overview($farm, $pondId);
        $expenses = $this->expensesForPondFilter($farm, $pondId);
        $row = collect($overview['by_pond'] ?? [])->firstWhere('pond_id', $pondId) ?: [];

        return [
            'locked_at' => now()->toIso8601String(),
            'total_expenses' => (float) ($overview['total_expenses'] ?? 0),
            'total_sales' => (float) ($overview['total_sales'] ?? 0),
            'profit' => (float) ($overview['profit'] ?? 0),
            'direct_expenses' => (float) ($row['direct_expenses'] ?? 0),
            'categories' => $overview['categories'] ?? [],
            'custom_sections' => $overview['custom_sections'] ?? [],
            'feed_allocated' => (float) ($overview['feed_allocated'] ?? 0),
            'medicine_allocated' => (float) ($overview['medicine_allocated'] ?? 0),
            'equipment_allocated' => (float) ($overview['equipment_allocated'] ?? 0),
            'feed_used_kg' => (float) ($row['feed_used_kg'] ?? 0),
            'medicine_used_kg' => (float) ($row['medicine_used_kg'] ?? 0),
            'expenses' => $expenses->map(fn (Expense $e) => $this->serializeExpense($e))->values()->all(),
        ];
    }

    public function pondFinanceLocked(Farm $farm, int $pondId): bool
    {
        $pond = Pond::query()->whereKey($pondId)->where('farm_id', $farm->id)->first();

        return $pond ? $this->isPondClosed($pond) : false;
    }

    /** @return array{id: int, name: string, status: string|null, closed_at: string|null, is_closed: bool} */
    private function pondPayload(Pond $pond): array
    {
        $closed = $pond->closed_at !== null
            || $pond->status === 'inactive'
            || $pond->status === 'closed';

        return [
            'id' => $pond->id,
            'name' => $pond->name,
            'status' => $pond->status,
            'closed_at' => optional($pond->closed_at)?->toIso8601String(),
            'is_closed' => $closed,
            'finance_locked' => $closed,
        ];
    }

    public function isPondClosed(Pond $pond): bool
    {
        return $pond->closed_at !== null
            || $pond->status === 'inactive'
            || $pond->status === 'closed';
    }

    /**
     * @param  Collection<int, int|string>  $pondIds
     * @return array<int, array>
     */
    private function financeSnapshotsByPond(Collection $pondIds): array
    {
        if ($pondIds->isEmpty()) {
            return [];
        }

        return PondClosure::query()
            ->whereIn('pond_id', $pondIds)
            ->orderByDesc('id')
            ->get(['pond_id', 'summary_snapshot'])
            ->unique('pond_id')
            ->mapWithKeys(function (PondClosure $closure) {
                $snap = data_get($closure->summary_snapshot, 'finance_snapshot');

                return is_array($snap) ? [$closure->pond_id => $snap] : [];
            })
            ->all();
    }

    /** @param  array<string, mixed>  $snap */
    private function pondRowFromSnapshot(Pond $pond, array $snap): array
    {
        return [
            'pond_id' => $pond->id,
            'name' => $pond->name,
            'status' => $pond->status,
            'total_expenses' => (float) ($snap['total_expenses'] ?? 0),
            'direct_expenses' => (float) ($snap['direct_expenses'] ?? 0),
            'feed_allocated' => (float) ($snap['feed_allocated'] ?? 0),
            'medicine_allocated' => (float) ($snap['medicine_allocated'] ?? 0),
            'equipment_allocated' => (float) ($snap['equipment_allocated'] ?? 0),
            'total_sales' => (float) ($snap['total_sales'] ?? 0),
            'profit' => (float) ($snap['profit'] ?? 0),
            'feed_used_kg' => (float) ($snap['feed_used_kg'] ?? 0),
            'medicine_used_kg' => (float) ($snap['medicine_used_kg'] ?? 0),
            'is_closed' => true,
            'finance_locked' => true,
        ];
    }

    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'farm_id' => $expense->farm_id,
            'pond_id' => $expense->pond_id,
            'user_id' => $expense->user_id,
            'category' => $expense->category,
            'title' => $expense->title,
            'quantity' => $expense->quantity,
            'unit_price' => $expense->unit_price,
            'amount' => (float) $expense->amount,
            'expense_date' => optional($expense->expense_date)?->toDateString() ?? $expense->expense_date,
            'description' => $expense->description,
            'allocated_from_farm' => (bool) $expense->getAttribute('allocated_from_farm'),
            'allocated_from_usage' => (bool) $expense->getAttribute('allocated_from_usage'),
            'original_amount' => $expense->getAttribute('original_amount'),
            'pond' => $expense->pond ? [
                'id' => $expense->pond->id,
                'name' => $expense->pond->name,
            ] : null,
            'user' => $expense->user ? [
                'id' => $expense->user->id,
                'name' => $expense->user->name,
            ] : null,
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function expenseFromSnapshotRow(array $row): Expense
    {
        $expense = new Expense();
        $expense->forceFill([
            'farm_id' => $row['farm_id'] ?? null,
            'pond_id' => $row['pond_id'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'category' => $row['category'] ?? null,
            'title' => $row['title'] ?? null,
            'quantity' => $row['quantity'] ?? null,
            'unit_price' => $row['unit_price'] ?? null,
            'amount' => $row['amount'] ?? 0,
            'expense_date' => $row['expense_date'] ?? null,
            'description' => $row['description'] ?? null,
        ]);
        $expense->id = $row['id'] ?? null;
        $expense->exists = true;
        $expense->setAttribute('allocated_from_farm', (bool) ($row['allocated_from_farm'] ?? false));
        $expense->setAttribute('allocated_from_usage', (bool) ($row['allocated_from_usage'] ?? false));
        $expense->setAttribute('original_amount', $row['original_amount'] ?? null);

        if (! empty($row['pond']['id'])) {
            $pond = new Pond();
            $pond->forceFill(['name' => $row['pond']['name'] ?? '']);
            $pond->id = $row['pond']['id'];
            $pond->exists = true;
            $expense->setRelation('pond', $pond);
        }
        if (! empty($row['user']['id'])) {
            $user = new User();
            $user->forceFill(['name' => $row['user']['name'] ?? '']);
            $user->id = $row['user']['id'];
            $user->exists = true;
            $expense->setRelation('user', $user);
        }

        return $expense;
    }

    /**
     * @param  Collection<int, Expense>  $allExpenses
     * @return array<string, float>
     */
    private function categoriesForPond(
        Collection $allExpenses,
        int $pondId,
        float $pondFeedKg,
        float $farmFeedKg,
        float $pondMedicineKg = 0.0,
        float $farmMedicineKg = 0.0,
        float $equipmentRatio = 0.0,
        ?Collection $ponds = null,
        ?Pond $pond = null
    ): array {
        $direct = $allExpenses
            ->where('pond_id', $pondId)
            ->reject(fn (Expense $e) => $this->isConsumableWarehouseCategory((string) $e->category));
        $byCategory = $direct
            ->groupBy('category')
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

        $feedShare = $this->allocatedFarmWarehouseAmount(
            $allExpenses,
            FinanceCategories::FEED,
            $pondFeedKg,
            $farmFeedKg
        );
        $feedDirect = (float) ($byCategory[FinanceCategories::FEED] ?? 0);
        $byCategory[FinanceCategories::FEED] = round($feedDirect + $feedShare, 2);

        $medicineShare = $this->allocatedFarmWarehouseAmount(
            $allExpenses,
            FinanceCategories::MEDICINE,
            $pondMedicineKg,
            $farmMedicineKg
        );
        $medicineDirect = (float) ($byCategory[FinanceCategories::MEDICINE] ?? 0);
        $byCategory[FinanceCategories::MEDICINE] = round($medicineDirect + $medicineShare, 2);

        $equipmentShare = ($ponds && $pond)
            ? $this->allocatedFarmEquipmentAmountForPond($allExpenses, $ponds, $pond)
            : $this->allocatedFarmEquipmentAmount($allExpenses, $equipmentRatio);
        $equipmentDirect = (float) ($byCategory[FinanceCategories::EQUIPMENT] ?? 0);
        $byCategory[FinanceCategories::EQUIPMENT] = round($equipmentDirect + $equipmentShare, 2);

        $categories = collect(FinanceCategories::EXPENSE)->mapWithKeys(
            fn (string $category) => [$category => (float) ($byCategory[$category] ?? 0)]
        )->all();

        foreach ($byCategory as $category => $total) {
            if (! array_key_exists($category, $categories)) {
                $categories[$category] = (float) $total;
            }
        }

        return $categories;
    }

    /**
     * @param  array<string, float>  $categories
     * @return list<array{id:int,name:string,category:string,total:float}>
     */
    private function customSections(Farm $farm, array $categories): array
    {
        return $farm->financeSections()
            ->orderBy('name')
            ->get()
            ->map(function (FinanceSection $section) use ($categories) {
                $key = $section->categoryKey();

                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'category' => $key,
                    'total' => (float) ($categories[$key] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $pondIds
     * @return Collection<int|string, float>
     */
    public function feedUsageByPond(Collection $pondIds): Collection
    {
        if ($pondIds->isEmpty()) {
            return collect();
        }

        $fromItems = DB::table('daily_report_items')
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_items.daily_report_id')
            ->join('warehouse_items', 'warehouse_items.id', '=', 'daily_report_items.warehouse_item_id')
            ->whereIn('daily_reports.pond_id', $pondIds)
            ->where('warehouse_items.category', WarehouseCategories::FEED)
            ->groupBy('daily_reports.pond_id')
            ->select('daily_reports.pond_id', DB::raw('SUM(daily_report_items.quantity) as total_kg'))
            ->pluck('total_kg', 'pond_id')
            ->map(fn ($v) => (float) $v);

        $fromColumns = DailyReport::query()
            ->select(
                'pond_id',
                DB::raw('SUM(COALESCE(feeding_kg, 0) + COALESCE(wheat_bran_kg, 0)) as total_kg')
            )
            ->whereIn('pond_id', $pondIds)
            ->groupBy('pond_id')
            ->pluck('total_kg', 'pond_id')
            ->map(fn ($v) => (float) $v);

        $merged = collect();
        foreach ($pondIds as $pondId) {
            $itemsKg = (float) ($fromItems[$pondId] ?? 0);
            $columnKg = (float) ($fromColumns[$pondId] ?? 0);
            $merged[$pondId] = $itemsKg > 0 ? $itemsKg : $columnKg;
        }

        return $merged;
    }

    /**
     * @param  Collection<int, int|string>  $pondIds
     * @return Collection<int|string, float>
     */
    public function medicineUsageByPond(Collection $pondIds): Collection
    {
        if ($pondIds->isEmpty()) {
            return collect();
        }

        return DB::table('daily_report_items')
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_items.daily_report_id')
            ->join('warehouse_items', 'warehouse_items.id', '=', 'daily_report_items.warehouse_item_id')
            ->whereIn('daily_reports.pond_id', $pondIds)
            ->where('warehouse_items.category', 'medicine')
            ->groupBy('daily_reports.pond_id')
            ->select('daily_reports.pond_id', DB::raw('SUM(daily_report_items.quantity) as total_kg'))
            ->pluck('total_kg', 'pond_id')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * Ombordagi yem/dori xarajati butun xo‘jalikka tegishli.
     * Kol hisobotiga faqat ishlatilgan kg × o‘rtacha kirim narxi tushadi.
     *
     * @param  Collection<int, Expense>  $allExpenses
     */
    public function allocatedFarmWarehouseAmount(
        Collection $allExpenses,
        string $category,
        float $pondUsedKg,
        float $farmUsedKg = 0.0
    ): float {
        $purchases = $allExpenses->where('category', $category);
        $purchasedAmount = round((float) $purchases->sum('amount'), 2);
        $purchasedQty = round((float) $purchases->sum(fn (Expense $e) => (float) ($e->quantity ?? 0)), 3);

        if ($pondUsedKg <= 0) {
            return 0.0;
        }

        if ($purchasedQty > 0 && $purchasedAmount > 0) {
            $used = min($pondUsedKg, $purchasedQty);

            return round($purchasedAmount * ($used / $purchasedQty), 2);
        }

        $priced = $purchases->filter(fn (Expense $e) => (float) ($e->unit_price ?? 0) > 0);
        if ($priced->isEmpty()) {
            return 0.0;
        }

        $avgPrice = (float) $priced->avg(fn (Expense $e) => (float) $e->unit_price);

        return round($pondUsedKg * $avgPrice, 2);
    }

    /**
     * Umumiy uskuna — ochiq/mavjud havzalarga teng ulush.
     *
     * @param  Collection<int, Pond>  $ponds
     * @return array<int, float> pond_id => share 0..1
     */
    public function equipmentAreaSplit(Collection $ponds): array
    {
        if ($ponds->isEmpty()) {
            return [];
        }

        $equal = 1 / $ponds->count();
        $split = [];
        foreach ($ponds as $pond) {
            $split[(int) $pond->id] = $equal;
        }

        return $split;
    }

    /**
     * @param  Collection<int, Expense>  $allExpenses
     */
    public function allocatedFarmEquipmentAmount(Collection $allExpenses, float $ratio): float
    {
        $farmWide = round((float) $allExpenses
            ->whereNull('pond_id')
            ->where('category', FinanceCategories::EQUIPMENT)
            ->sum('amount'), 2);

        if ($farmWide <= 0 || $ratio <= 0) {
            return 0.0;
        }

        return round($farmWide * $ratio, 2);
    }

    /**
     * @param  Collection<int, Pond>  $ponds
     */
    public function allocatedFarmEquipmentAmountForPond(Collection $allExpenses, Collection $ponds, Pond $target): float
    {
        $farmWide = $allExpenses
            ->whereNull('pond_id')
            ->where('category', FinanceCategories::EQUIPMENT);

        $total = 0.0;
        foreach ($farmWide as $expense) {
            $eligible = $this->pondsOpenOn($ponds, $expense->expense_date ?? null);
            if ($eligible->isEmpty() || ! $eligible->contains(fn (Pond $p) => (int) $p->id === (int) $target->id)) {
                continue;
            }
            $ratio = (float) ($this->equipmentAreaSplit($eligible)[(int) $target->id] ?? 0);
            if ($ratio <= 0) {
                continue;
            }
            $total += (float) $expense->amount * $ratio;
        }

        return round($total, 2);
    }

    /**
     * @param  Collection<int, Pond>  $ponds
     * @return Collection<int, Pond>
     */
    private function pondsOpenOn(Collection $ponds, mixed $date): Collection
    {
        $day = $date
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();

        return $ponds->filter(function (Pond $pond) use ($day) {
            if (! $this->isPondClosed($pond)) {
                return true;
            }
            $closedOn = optional($pond->closed_at)->toDateString();

            return $closedOn && $day <= $closedOn;
        })->values();
    }

    /** @deprecated Use allocatedFarmWarehouseAmount */
    public function allocatedFarmFeedAmount(Collection $allExpenses, float $pondFeedKg, float $farmFeedKg): float
    {
        return $this->allocatedFarmWarehouseAmount(
            $allExpenses,
            FinanceCategories::FEED,
            $pondFeedKg,
            $farmFeedKg
        );
    }

    /**
     * Effective expense amount for a pond filter (direct + allocated farm feed rows as virtual).
     *
     * @return Collection<int, array>
     */
    public function expensesForPondFilter(Farm $farm, ?int $pondId, ?string $category = null): Collection
    {
        $query = Expense::query()
            ->with(['user:id,name', 'pond:id,name'])
            ->where('farm_id', $farm->id);

        if ($category) {
            $query->where('category', $category);
        }

        if (! $pondId) {
            return $query->latest('expense_date')->latest('id')->get();
        }

        $pond = Pond::query()->whereKey($pondId)->first(['id', 'name', 'farm_id', 'closed_at', 'status']);
        if ($pond && $this->isPondClosed($pond)) {
            $snapshot = $this->financeSnapshotsByPond(collect([$pondId]))[$pondId] ?? null;
            if (is_array($snapshot) && isset($snapshot['expenses']) && is_array($snapshot['expenses'])) {
                $rows = collect($snapshot['expenses'])->map(fn (array $row) => $this->expenseFromSnapshotRow($row));
                if ($category) {
                    $rows = $rows->filter(fn (Expense $e) => $e->category === $category);
                }

                return $rows->values();
            }
        }

        $direct = (clone $query)
            ->where('pond_id', $pondId)
            ->when(
                ! $category || $this->isConsumableWarehouseCategory($category),
                fn ($q) => $q->whereNotIn('category', [FinanceCategories::FEED, FinanceCategories::MEDICINE])
            )
            ->latest('expense_date')
            ->latest('id')
            ->get();

        $rows = $direct->map(fn (Expense $e) => $e);

        $pond = $pond ?: Pond::query()->whereKey($pondId)->first(['id', 'name', 'farm_id', 'closed_at', 'status']);
        $purchases = Expense::query()
            ->where('farm_id', $farm->id)
            ->whereIn('category', [FinanceCategories::FEED, FinanceCategories::MEDICINE])
            ->get(['id', 'category', 'title', 'quantity', 'unit_price', 'amount', 'warehouse_item_id']);

        if (! $category || $category === FinanceCategories::FEED) {
            foreach ($this->pondFeedUsageExpenses($farm, $pond, $purchases) as $row) {
                $rows->push($row);
            }
        }
        if (! $category || $category === FinanceCategories::MEDICINE) {
            foreach ($this->pondMedicineUsageExpenses($farm, $pond, $purchases) as $row) {
                $rows->push($row);
            }
        }

        if (! $category || $category === FinanceCategories::EQUIPMENT) {
            $ponds = $farm->ponds()->orderBy('name')->get(['id', 'area_m2', 'closed_at', 'status']);
            $farmWideEquipment = Expense::query()
                ->with(['user:id,name'])
                ->where('farm_id', $farm->id)
                ->whereNull('pond_id')
                ->where('category', FinanceCategories::EQUIPMENT)
                ->latest('expense_date')
                ->latest('id')
                ->get();

            foreach ($farmWideEquipment as $expense) {
                $eligible = $this->pondsOpenOn($ponds, $expense->expense_date);
                if ($eligible->isEmpty() || ! $eligible->contains(fn (Pond $p) => (int) $p->id === (int) $pondId)) {
                    continue;
                }
                $share = round((float) $expense->amount / $eligible->count(), 2);
                if ($share <= 0) {
                    continue;
                }
                $clone = $expense->replicate();
                $clone->id = $expense->id;
                $clone->amount = $share;
                $clone->setAttribute('allocated_from_farm', true);
                $clone->setAttribute('original_amount', (float) $expense->amount);
                $clone->setRelation('user', $expense->user);
                $rows->push($clone);
            }
        }

        return $rows->sortByDesc(fn (Expense $e) => [$e->expense_date?->format('Y-m-d'), $e->id])->values();
    }

    private function isConsumableWarehouseCategory(string $category): bool
    {
        return in_array($category, [FinanceCategories::FEED, FinanceCategories::MEDICINE], true);
    }

    /**
     * @param  Collection<int, Expense>  $purchases
     * @return list<Expense>
     */
    private function pondFeedUsageExpenses(Farm $farm, ?Pond $pond, Collection $purchases): array
    {
        if (! $pond) {
            return [];
        }

        $reports = DailyReport::query()
            ->with(['user:id,name', 'items.warehouseItem'])
            ->where('pond_id', $pond->id)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();

        $rows = [];
        foreach ($reports as $report) {
            $feedItems = $report->items->filter(
                fn ($item) => $item->warehouseItem
                    && $item->warehouseItem->category === WarehouseCategories::FEED
            );

            if ($feedItems->isNotEmpty()) {
                foreach ($feedItems as $item) {
                    $qty = (float) $item->quantity;
                    if ($qty <= 0) {
                        continue;
                    }
                    $price = $this->purchaseUnitPrice(
                        $purchases,
                        FinanceCategories::FEED,
                        (int) $item->warehouse_item_id,
                        $item->warehouseItem->name
                    );
                    if ($price <= 0) {
                        $price = (float) ($item->warehouseItem->unit_price ?? 0);
                    }
                    $amount = round($qty * $price, 2);
                    if ($amount <= 0) {
                        continue;
                    }
                    $rows[] = $this->makeUsageExpense([
                        'id' => -1 * (1000000 + (int) $item->id),
                        'farm_id' => $farm->id,
                        'pond_id' => $pond->id,
                        'user_id' => $report->user_id,
                        'category' => FinanceCategories::FEED,
                        'title' => $item->warehouseItem->name,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'amount' => $amount,
                        'expense_date' => $report->report_date,
                        'description' => 'Kunlik yem sarfi',
                    ], $pond, $report->user);
                }

                continue;
            }

            $qty = round((float) $report->feeding_kg + (float) $report->wheat_bran_kg, 3);
            if ($qty <= 0) {
                continue;
            }
            $title = trim((string) $report->feed_name) !== '' ? trim((string) $report->feed_name) : 'Yem';
            $price = $this->purchaseUnitPrice($purchases, FinanceCategories::FEED, null, $title);
            $amount = round($qty * $price, 2);
            if ($amount <= 0) {
                continue;
            }
            $rows[] = $this->makeUsageExpense([
                'id' => -1 * (2000000 + (int) $report->id),
                'farm_id' => $farm->id,
                'pond_id' => $pond->id,
                'user_id' => $report->user_id,
                'category' => FinanceCategories::FEED,
                'title' => $title,
                'quantity' => $qty,
                'unit_price' => $price,
                'amount' => $amount,
                'expense_date' => $report->report_date,
                'description' => 'Kunlik yem sarfi',
            ], $pond, $report->user);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Expense>  $purchases
     * @return list<Expense>
     */
    private function pondMedicineUsageExpenses(Farm $farm, ?Pond $pond, Collection $purchases): array
    {
        if (! $pond) {
            return [];
        }

        $items = DB::table('daily_report_items')
            ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_items.daily_report_id')
            ->join('warehouse_items', 'warehouse_items.id', '=', 'daily_report_items.warehouse_item_id')
            ->leftJoin('users', 'users.id', '=', 'daily_reports.user_id')
            ->where('daily_reports.pond_id', $pond->id)
            ->where('warehouse_items.category', WarehouseCategories::MEDICINE)
            ->orderByDesc('daily_reports.report_date')
            ->orderByDesc('daily_report_items.id')
            ->get([
                'daily_report_items.id',
                'daily_report_items.quantity',
                'daily_report_items.warehouse_item_id',
                'daily_reports.report_date',
                'daily_reports.user_id',
                'warehouse_items.name as item_name',
                'warehouse_items.unit_price as item_unit_price',
                'users.name as user_name',
            ]);

        $rows = [];
        foreach ($items as $item) {
            $qty = (float) $item->quantity;
            if ($qty <= 0) {
                continue;
            }
            $price = $this->purchaseUnitPrice(
                $purchases,
                FinanceCategories::MEDICINE,
                (int) $item->warehouse_item_id,
                (string) $item->item_name
            );
            if ($price <= 0) {
                $price = (float) ($item->item_unit_price ?? 0);
            }
            $amount = round($qty * $price, 2);
            if ($amount <= 0) {
                continue;
            }
            $user = null;
            if ($item->user_id) {
                $user = new \App\Models\User();
                $user->id = (int) $item->user_id;
                $user->name = $item->user_name;
            }
            $rows[] = $this->makeUsageExpense([
                'id' => -1 * (3000000 + (int) $item->id),
                'farm_id' => $farm->id,
                'pond_id' => $pond->id,
                'user_id' => $item->user_id,
                'category' => FinanceCategories::MEDICINE,
                'title' => $item->item_name,
                'quantity' => $qty,
                'unit_price' => $price,
                'amount' => $amount,
                'expense_date' => $item->report_date,
                'description' => 'Kunlik dori sarfi',
            ], $pond, $user);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Expense>  $purchases
     */
    private function purchaseUnitPrice(
        Collection $purchases,
        string $category,
        ?int $warehouseItemId,
        ?string $name
    ): float {
        $rows = $purchases->where('category', $category);
        if ($warehouseItemId) {
            $matched = $rows->where('warehouse_item_id', $warehouseItemId);
            if ($matched->isNotEmpty()) {
                $rows = $matched;
            }
        } elseif ($name) {
            $needle = mb_strtolower(trim($name));
            $matched = $rows->filter(
                fn (Expense $e) => mb_strtolower(trim((string) $e->title)) === $needle
            );
            if ($matched->isNotEmpty()) {
                $rows = $matched;
            }
        }

        $qty = (float) $rows->sum(fn (Expense $e) => (float) ($e->quantity ?? 0));
        $amount = (float) $rows->sum('amount');
        if ($qty > 0 && $amount > 0) {
            return round($amount / $qty, 2);
        }

        $priced = $rows->first(fn (Expense $e) => (float) ($e->unit_price ?? 0) > 0);

        return $priced ? (float) $priced->unit_price : 0.0;
    }

    private function makeUsageExpense(array $attrs, Pond $pond, $user = null): Expense
    {
        $expense = new Expense();
        $expense->forceFill($attrs);
        $expense->id = (int) $attrs['id'];
        $expense->exists = false;
        $expense->setAttribute('allocated_from_farm', true);
        $expense->setAttribute('allocated_from_usage', true);
        $expense->setAttribute('original_amount', (float) $attrs['amount']);
        $expense->syncOriginal();
        $expense->setRelation('pond', $pond);
        if ($user) {
            $expense->setRelation('user', $user);
        }

        return $expense;
    }
}
