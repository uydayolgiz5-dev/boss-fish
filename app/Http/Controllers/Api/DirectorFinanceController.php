<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FinanceSection;
use App\Models\Pond;
use App\Services\FarmFinanceService;
use App\Services\FinanceWarehouseSync;
use App\Support\FarmAccess;
use App\Support\FinanceCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DirectorFinanceController extends Controller
{
    public function __construct(
        private readonly FinanceWarehouseSync $financeWarehouse,
        private readonly FarmFinanceService $farmFinance,
    ) {}

    public function overview(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $pondId = $request->filled('pond_id') ? (int) $request->query('pond_id') : null;

        return response()->json($this->farmFinance->overview($farm, $pondId));
    }

    public function sections(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $sections = $farm->financeSections()
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return response()->json(['data' => $sections]);
    }

    public function storeSection(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('finance_sections', 'name')->where('farm_id', $farm->id),
            ],
        ]);

        $section = FinanceSection::create([
            'farm_id' => $farm->id,
            'name' => trim($data['name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $section->id,
            'name' => $section->name,
            'category' => $section->categoryKey(),
        ], 201);
    }

    public function destroySection(Request $request, Farm $farm, FinanceSection $section): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureSectionBelongsToFarm($section, $farm);

        $hasExpenses = Expense::query()
            ->where('farm_id', $farm->id)
            ->where('category', $section->categoryKey())
            ->exists();

        if ($hasExpenses) {
            abort(422, 'Bu bo‘limda xarajatlar bor. Avval ularni boshqa bo‘limga ko‘chiring yoki o‘chiring.');
        }

        $section->delete();

        return response()->json(['message' => 'Bo‘lim o‘chirildi']);
    }

    public function expenses(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $category = $request->string('category')->toString();
        if ($category !== '' && ! $this->isValidCategoryForFarm($farm, $category)) {
            abort(422, 'Noto‘g‘ri xarajat bo‘limi');
        }

        $pondId = $request->filled('pond_id') ? (int) $request->query('pond_id') : null;
        if ($pondId) {
            $this->ensurePondBelongsToFarm($farm, $pondId);
        }

        $expenses = $this->farmFinance->expensesForPondFilter(
            $farm,
            $pondId,
            $category !== '' ? $category : null
        );

        if ($request->from) {
            $from = $request->from;
            $expenses = $expenses->filter(fn (Expense $e) => $e->expense_date >= $from);
        }
        if ($request->to) {
            $to = $request->to;
            $expenses = $expenses->filter(fn (Expense $e) => $e->expense_date <= $to);
        }

        $expenses = $expenses->values();

        $categoryTotal = $category !== ''
            ? round((float) $expenses->sum('amount'), 2)
            : null;

        return response()->json([
            'data' => $expenses,
            'category_total' => $categoryTotal,
            'pond_id' => $pondId,
            'finance_locked' => $pondId ? $this->farmFinance->pondFinanceLocked($farm, $pondId) : false,
        ]);
    }

    public function storeExpense(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarm($request, $farm);

        $data = $this->validateExpense($request, $farm);
        $this->assertNotWarehouseOriginCategory((string) ($data['category'] ?? ''));

        $expense = Expense::create([
            ...$data,
            'farm_id' => $farm->id,
            'user_id' => $request->user()->id,
        ]);

        // Yem/dori/uskuna bu yerda yaratilmaydi — apply faqat eski nomoliyaviy yo‘l uchun no-op
        $this->financeWarehouse->apply($expense, $farm, $request->user());

        return response()->json($expense->fresh()->load(['user:id,name', 'pond:id,name']), 201);
    }

    public function updateExpense(Request $request, Farm $farm, Expense $expense): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureExpenseBelongsToFarm($expense, $farm);
        $this->assertExpenseNotWarehouseManaged($expense);

        $this->ensureExpenseMutable($expense, $farm);

        $data = $this->validateExpense($request, $farm, partial: true);
        if (isset($data['category'])) {
            $this->assertNotWarehouseOriginCategory((string) $data['category']);
        }
        $before = $expense->fresh();

        $expense->update($data);
        $this->financeWarehouse->resync($expense->fresh(), $farm, $request->user(), $before);

        return response()->json($expense->fresh()->load(['user:id,name', 'pond:id,name']));
    }

    public function destroyExpense(Request $request, Farm $farm, Expense $expense): JsonResponse
    {
        $this->authorizeFarm($request, $farm);
        $this->ensureExpenseBelongsToFarm($expense, $farm);
        $this->assertExpenseNotWarehouseManaged($expense);
        $this->ensureExpenseMutable($expense, $farm);

        $this->financeWarehouse->reverse($expense);
        $expense->delete();

        return response()->json(['message' => 'Xarajat o‘chirildi']);
    }

    private function validateExpense(Request $request, Farm $farm, bool $partial = false): array
    {
        $rules = [
            'category' => $partial ? 'sometimes|required|string|max:64' : 'required|string|max:64',
            'title' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            'expense_date' => ($partial ? 'sometimes' : 'required').'|date',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0.01',
            'description' => 'nullable|string',
            'pond_id' => 'nullable|integer',
            'scope' => 'nullable|in:farm,pond,all',
        ];

        $data = $request->validate($rules);

        $category = $data['category'] ?? $request->input('category');
        if ($category && FinanceCategories::originatesFromWarehouse((string) $category)) {
            abort(422, 'Yem, dori va uskuna Ombor → Kirim orqali kiritiladi. Moliyadan kiritilmaydi.');
        }
        if ($category && ! $this->isValidCategoryForFarm($farm, $category)) {
            abort(422, 'Noto‘g‘ri xarajat bo‘limi');
        }

        $scope = $data['scope'] ?? null;
        unset($data['scope']);

        if ($scope === 'farm' || $scope === 'all') {
            $data['pond_id'] = null;
        } elseif (array_key_exists('pond_id', $data)) {
            if ($data['pond_id'] === null || $data['pond_id'] === '' || $data['pond_id'] === 'all') {
                $data['pond_id'] = null;
            } else {
                $pondId = (int) $data['pond_id'];
                $this->ensurePondOpenForExpense($farm, $pondId);
                $data['pond_id'] = $pondId;
            }
        } elseif (! $partial) {
            // Yangi yozuvda scope/pond_id majburiy emas — default butun xo‘jalik
            $data['pond_id'] = null;
        }

        $quantity = isset($data['quantity']) ? (float) $data['quantity'] : null;
        $unitPrice = isset($data['unit_price']) ? (float) $data['unit_price'] : null;
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        $qtyOk = $quantity !== null && $quantity > 0;
        $priceOk = $unitPrice !== null && $unitPrice > 0;
        $amountOk = $amount !== null && $amount > 0;

        if ($qtyOk && $priceOk) {
            $data['amount'] = round($quantity * $unitPrice, 2);
            $data['quantity'] = $quantity;
            $data['unit_price'] = $unitPrice;
        } elseif ($qtyOk && $amountOk) {
            $data['quantity'] = $quantity;
            $data['amount'] = round($amount, 2);
            $data['unit_price'] = round($amount / $quantity, 2);
        } elseif ($priceOk && $amountOk) {
            $data['unit_price'] = $unitPrice;
            $data['amount'] = round($amount, 2);
            $data['quantity'] = round($amount / $unitPrice, 3);
        } elseif ($amountOk && ! FinanceCategories::syncsToWarehouse((string) $category)) {
            $data['amount'] = round($amount, 2);
        } elseif (! $partial) {
            abort(422, FinanceCategories::syncsToWarehouse((string) $category)
                ? 'Yem/dori uchun miqdor, narx, summadan kamida ikkitasini kiriting'
                : 'Summa yoki miqdor × narx kiriting');
        }

        if (
            $category
            && FinanceCategories::syncsToWarehouse($category)
            && (! $partial || array_key_exists('quantity', $data) || array_key_exists('amount', $data))
        ) {
            $syncQuantity = isset($data['quantity']) ? (float) $data['quantity'] : 0;
            if ($syncQuantity <= 0) {
                abort(422, 'Yem va dori uchun miqdor (kg) kiriting — omborga avtomatik tushadi');
            }
        }

        if ($category && ! FinanceCategories::syncsToWarehouse($category)) {
            $data['quantity'] = $data['quantity'] ?? null;
            $data['unit_price'] = $data['unit_price'] ?? null;
        }

        return $data;
    }

    private function ensurePondBelongsToFarm(Farm $farm, int $pondId): void
    {
        $exists = Pond::query()
            ->whereKey($pondId)
            ->where('farm_id', $farm->id)
            ->exists();

        if (! $exists) {
            abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
        }
    }

    private function ensureExpenseMutable(Expense $expense, Farm $farm): void
    {
        if ($expense->pond_id) {
            $this->ensurePondOpenForExpense($farm, (int) $expense->pond_id);
        }
    }

    private function ensurePondOpenForExpense(Farm $farm, int $pondId): void
    {
        $pond = Pond::query()
            ->whereKey($pondId)
            ->where('farm_id', $farm->id)
            ->first(['id', 'closed_at', 'status']);

        if (! $pond) {
            abort(422, 'Havza topilmadi yoki bu xo‘jalikka tegishli emas');
        }

        if ($pond->closed_at !== null || $pond->status === 'inactive' || $pond->status === 'closed') {
            abort(422, 'Yopilgan havzaga yangi xarajat kiritib bo‘lmaydi. Keyingi mavsumni oching yoki butun xo‘jalikni tanlang.');
        }
    }

    private function isValidCategoryForFarm(Farm $farm, string $category): bool
    {
        if (FinanceCategories::isFixed($category)) {
            return true;
        }

        if (! FinanceCategories::isCustom($category)) {
            return false;
        }

        $sectionId = FinanceCategories::customId($category);

        return $sectionId
            && $farm->financeSections()->whereKey($sectionId)->exists();
    }

    private function authorizeFarm(Request $request, Farm $farm): void
    {
        FarmAccess::authorizeDirectorOrOwner($request->user(), $farm);
    }

    private function ensureExpenseBelongsToFarm(Expense $expense, Farm $farm): void
    {
        if ((int) $expense->farm_id !== (int) $farm->id) {
            abort(404);
        }
    }

    private function ensureSectionBelongsToFarm(FinanceSection $section, Farm $farm): void
    {
        if ((int) $section->farm_id !== (int) $farm->id) {
            abort(404);
        }
    }

    private function assertNotWarehouseOriginCategory(string $category): void
    {
        if (FinanceCategories::originatesFromWarehouse($category)) {
            abort(422, 'Yem, dori va uskuna Ombor → Kirim orqali kiritiladi. Moliyadan kiritilmaydi.');
        }
    }

    private function assertExpenseNotWarehouseManaged(Expense $expense): void
    {
        if (
            FinanceCategories::originatesFromWarehouse((string) $expense->category)
            || $expense->warehouse_item_id
        ) {
            abort(422, 'Bu xarajat Ombordan kelgan. O‘zgartirish yoki o‘chirish uchun Ombor bo‘limidan foydalaning.');
        }
    }
}
