<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\FishOperation;
use App\Models\MainReport;
use App\Models\Pond;
use App\Support\PricingPlans;
use App\Support\WarehouseCategories;
use App\Services\PondProblemAlerts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CompanyInspectionService
{
    public const LOOKBACK_DAYS = 10;

    public const WATER_TESTS_LIMIT = 5;

    public const CONTROLS_LIMIT = 2;

    public const LOSSES_LIMIT = 10;

    /** Moliya monitoring oralig‘i (joriy va oldingi davr). */
    public const FINANCE_LOOKBACK_DAYS = 10;

    /** Ketma-ket 0 so‘m kunlar ogohlantirishi. */
    public const FINANCE_ZERO_STREAK = 3;

    /** Kunlik xarajat anomal: o‘rtachadan shuncha marta katta. */
    public const FINANCE_SPIKE_RATIO = 2.5;

    /**
     * Tekshiruv: tanlangan havza bo‘yicha oxirgi 10 kun.
     * Rang: yem+dori = yashil, faqat bittasi = sariq, ikkalasi yo‘q yoki hisobot yo‘q = qizil.
     *
     * @return array<string, mixed>
     */
    public function inspect(Company $company, ?int $pondId = null): array
    {
        $company->loadMissing(['admin']);
        $admin = $company->admin;

        $openPonds = Pond::query()
            ->where('company_id', $company->id)
            ->whereNull('closed_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $pondList = $openPonds->map(fn (Pond $p) => [
            'id' => $p->id,
            'name' => $p->name ?: ('#'.$p->id),
        ])->values()->all();

        $selectedPond = null;
        if ($pondId !== null) {
            $selectedPond = $openPonds->firstWhere('id', $pondId);
            if (! $selectedPond) {
                throw ValidationException::withMessages([
                    'pond_id' => ['Havza topilmadi yoki yopiq.'],
                ]);
            }
        } elseif ($openPonds->count() === 1) {
            $selectedPond = $openPonds->first();
            $pondId = (int) $selectedPond->id;
        }

        $base = [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'is_active' => (bool) $company->is_active,
                'plan' => $company->plan,
                'access_expires_at' => optional($company->access_expires_at)?->toIso8601String(),
                'access_days_left' => $company->access_days_left,
                'needs_check' => $company->needsCheck(),
                'next_check_at' => optional($company->next_check_at)?->toDateString(),
                'last_checked_at' => optional($company->last_checked_at)?->toIso8601String(),
                'check_interval_days' => (int) ($company->check_interval_days ?? 0),
                'attention_note' => $company->attention_note,
                'admin' => $admin ? [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'login' => $admin->login,
                    'phone' => $admin->phone,
                ] : null,
            ],
            'ponds' => $pondList,
            'pond' => $selectedPond ? [
                'id' => $selectedPond->id,
                'name' => $selectedPond->name ?: ('#'.$selectedPond->id),
            ] : null,
            'manage' => [
                'home' => 'director-home',
                'reports' => 'director-fishery-ponds',
                'main_report' => 'director-main-report',
                'finance' => 'director-finance',
            ],
            'inspected_at' => now()->toIso8601String(),
        ];

        // Havza tanlanmaguncha faqat ro‘yxat
        if (! $pondId || ! $selectedPond) {
            return array_merge($base, [
                'period' => null,
                'days' => [],
                'counts' => [
                    'green' => 0,
                    'yellow' => 0,
                    'red' => 0,
                    'missing' => 0,
                    'total' => 0,
                ],
                'water_tests' => [],
                'controls' => [],
                'losses' => [],
                'finance' => null,
                'problem_alerts' => PondProblemAlerts::forCompanyInspection((int) $company->id, null),
                'needs_pond' => true,
                'can_complete' => false,
            ]);
        }

        $pondIds = collect([(int) $pondId]);
        $end = now()->startOfDay();
        $start = $end->copy()->subDays(self::LOOKBACK_DAYS - 1);

        $reports = DailyReport::query()
            ->with([
                'pond:id,name',
                'items.warehouseItem:id,name,category,unit',
            ])
            ->where('pond_id', $pondId)
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('report_date')
            ->orderBy('id')
            ->get();

        $byDate = $reports->groupBy(fn (DailyReport $r) => Carbon::parse($r->report_date)->toDateString());

        $days = [];
        for ($i = 0; $i < self::LOOKBACK_DAYS; $i++) {
            $date = $end->copy()->subDays(self::LOOKBACK_DAYS - 1 - $i);
            $key = $date->toDateString();
            $dayReports = $byDate->get($key, collect());
            $days[] = $this->buildDayCard($date, $dayReports);
        }

        $counts = [
            'green' => collect($days)->where('color', 'green')->count(),
            'yellow' => collect($days)->where('color', 'yellow')->count(),
            'red' => collect($days)->where('color', 'red')->count(),
            'missing' => collect($days)->where('has_report', false)->count(),
            'total' => count($days),
        ];

        return array_merge($base, [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => self::LOOKBACK_DAYS,
            ],
            'days' => $days,
            'counts' => $counts,
            'water_tests' => $this->buildWaterTestCards($pondIds),
            'controls' => $this->buildControlCards($pondIds),
            'losses' => $this->buildLossCards($pondIds),
            'finance' => $this->buildFinanceSnapshot((int) $pondId, $end),
            'problem_alerts' => PondProblemAlerts::forCompanyInspection((int) $company->id, (int) $pondId),
            'needs_pond' => false,
            'can_complete' => true,
        ]);
    }

    /**
     * Yuzaki moliya: jami, oldingi davr %, top 3, flaglar, muammoli kunlar.
     *
     * @return array<string, mixed>
     */
    private function buildFinanceSnapshot(int $pondId, Carbon $end): array
    {
        $days = self::FINANCE_LOOKBACK_DAYS;
        $currentStart = $end->copy()->subDays($days - 1);
        $prevEnd = $currentStart->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        $current = Expense::query()
            ->where('pond_id', $pondId)
            ->whereBetween('expense_date', [$currentStart->toDateString(), $end->toDateString()])
            ->get(['id', 'category', 'amount', 'expense_date', 'warehouse_item_id', 'title']);

        $previousTotal = (float) Expense::query()
            ->where('pond_id', $pondId)
            ->whereBetween('expense_date', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->sum('amount');

        $currentTotal = round((float) $current->sum('amount'), 2);
        $previousTotal = round($previousTotal, 2);

        $changePct = null;
        if ($previousTotal > 0) {
            $changePct = round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1);
        } elseif ($currentTotal > 0) {
            $changePct = 100.0;
        } else {
            $changePct = 0.0;
        }

        $warehouseCats = ['feed', 'medicine', 'equipment'];

        $byCategory = $current
            ->groupBy(fn (Expense $e) => (string) ($e->category ?: 'other'))
            ->map(fn (Collection $rows, string $cat) => [
                'category' => $cat,
                'amount' => round((float) $rows->sum('amount'), 2),
                'warehouse' => in_array($cat, $warehouseCats, true),
            ])
            ->sortByDesc('amount')
            ->values();

        $categories = $byCategory->map(function (array $row) use ($currentTotal) {
            $row['pct'] = $currentTotal > 0
                ? round(($row['amount'] / $currentTotal) * 100, 1)
                : 0.0;

            return $row;
        })->all();

        $topCategories = array_slice($categories, 0, 3);

        $misplaced = $current->filter(function (Expense $e) use ($warehouseCats) {
            return in_array((string) $e->category, $warehouseCats, true)
                && empty($e->warehouse_item_id);
        });

        $dailyTotals = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $currentStart->copy()->addDays($i)->toDateString();
            $sum = round((float) $current
                ->filter(fn (Expense $e) => optional($e->expense_date)?->toDateString() === $date)
                ->sum('amount'), 2);
            $dailyTotals[] = ['date' => $date, 'amount' => $sum];
        }

        $nonZero = collect($dailyTotals)->where('amount', '>', 0)->pluck('amount');
        $avgNonZero = $nonZero->count() > 0 ? (float) $nonZero->avg() : 0.0;

        $flags = [];
        $problemDays = [];

        if ($misplaced->isNotEmpty()) {
            $flags[] = [
                'type' => 'misplaced',
                'level' => 'danger',
                'count' => $misplaced->count(),
                'amount' => round((float) $misplaced->sum('amount'), 2),
            ];
        }

        // Ketma-ket 0 so‘m (davr ichida eng uzun zanjir)
        $streak = 0;
        $maxStreak = 0;
        $streakEnd = null;
        foreach ($dailyTotals as $row) {
            if ($row['amount'] <= 0) {
                $streak++;
                if ($streak >= $maxStreak) {
                    $maxStreak = $streak;
                    $streakEnd = $row['date'];
                }
            } else {
                $streak = 0;
            }
        }
        if ($maxStreak >= self::FINANCE_ZERO_STREAK && $currentTotal > 0) {
            $flags[] = [
                'type' => 'zero_streak',
                'level' => 'caution',
                'days' => $maxStreak,
                'ended_on' => $streakEnd,
            ];
        }

        foreach ($dailyTotals as $row) {
            if ($avgNonZero > 0 && $row['amount'] >= $avgNonZero * self::FINANCE_SPIKE_RATIO) {
                $problemDays[] = [
                    'date' => $row['date'],
                    'weekday' => Carbon::parse($row['date'])->locale('uz')->isoFormat('dddd'),
                    'amount' => $row['amount'],
                    'flags' => ['spike'],
                    'color' => 'red',
                ];
            }
        }

        if (count($problemDays) > 0) {
            $flags[] = [
                'type' => 'spike',
                'level' => 'caution',
                'count' => count($problemDays),
            ];
        }

        $level = 'ok';
        foreach ($flags as $f) {
            if (($f['level'] ?? '') === 'danger') {
                $level = 'danger';
                break;
            }
            if (($f['level'] ?? '') === 'caution') {
                $level = 'caution';
            }
        }

        return [
            'days' => $days,
            'period' => [
                'start' => $currentStart->toDateString(),
                'end' => $end->toDateString(),
                'prev_start' => $prevStart->toDateString(),
                'prev_end' => $prevEnd->toDateString(),
            ],
            'current_total' => $currentTotal,
            'previous_total' => $previousTotal,
            'change_pct' => $changePct,
            'categories' => $categories,
            'top_categories' => $topCategories,
            'flags' => $flags,
            'problem_days' => $problemDays,
            'level' => $level,
            'expense_count' => $current->count(),
        ];
    }

    /**
     * Oxirgi 5 ta suv testi.
     *
     * @param  Collection<int, int|string>  $pondIds
     * @return list<array<string, mixed>>
     */
    private function buildWaterTestCards(Collection $pondIds): array
    {
        $ids = $pondIds->all() ?: [-1];

        $reports = MainReport::query()
            ->with(['pond:id,name', 'user:id,name'])
            ->whereIn('pond_id', $ids)
            ->where(function ($q) {
                $q->whereNotNull('do_mg_l')
                    ->orWhereNotNull('ph_level')
                    ->orWhereNotNull('ammonia_mg_l')
                    ->orWhereNotNull('nitrite_mg_l')
                    ->orWhereNotNull('water_transparency_cm');
            })
            ->latest('report_date')
            ->latest('id')
            ->limit(self::WATER_TESTS_LIMIT)
            ->get();

        return $reports->reverse()->values()->map(function (MainReport $report) {
            $date = optional($report->report_date);
            $time = $report->test_time;
            if (is_string($time) && strlen($time) >= 5) {
                $time = substr($time, 0, 5);
            } elseif ($time) {
                $time = optional($time)->format('H:i');
            } else {
                $time = null;
            }

            return [
                'id' => $report->id,
                'date' => $date?->toDateString(),
                'weekday' => $date ? $date->copy()->locale('uz')->isoFormat('dddd') : '',
                'test_time' => $time,
                'color' => 'sky',
                'pond_id' => $report->pond_id,
                'pond_name' => $report->pond?->name ?: ('#'.$report->pond_id),
                'do_mg_l' => $report->do_mg_l !== null ? (float) $report->do_mg_l : null,
                'ph_level' => $report->ph_level !== null ? (float) $report->ph_level : null,
                'ammonia_mg_l' => $report->ammonia_mg_l !== null ? (float) $report->ammonia_mg_l : null,
                'nitrite_mg_l' => $report->nitrite_mg_l !== null ? (float) $report->nitrite_mg_l : null,
                'water_transparency_cm' => $report->water_transparency_cm !== null
                    ? (float) $report->water_transparency_cm
                    : null,
                'submitted_by' => $report->user?->name,
            ];
        })->all();
    }

    /**
     * Oxirgi 2 ta kontrol (baliq tortish).
     *
     * @param  Collection<int, int|string>  $pondIds
     * @return list<array<string, mixed>>
     */
    private function buildControlCards(Collection $pondIds): array
    {
        $ids = $pondIds->all() ?: [-1];

        $reports = MainReport::query()
            ->with(['pond:id,name', 'user:id,name', 'fishWeights'])
            ->whereIn('pond_id', $ids)
            ->whereHas('fishWeights')
            ->latest('report_date')
            ->latest('id')
            ->limit(self::CONTROLS_LIMIT)
            ->get();

        return $reports->reverse()->values()->map(function (MainReport $report) {
            $date = optional($report->report_date);
            $weights = $report->fishWeights->map(function ($w) {
                $g = round(((float) $w->average_weight_kg) * 1000, 1);

                return [
                    'fish_type' => (string) $w->fish_type,
                    'average_weight_g' => $g,
                ];
            })->values()->all();

            return [
                'id' => $report->id,
                'date' => $date?->toDateString(),
                'weekday' => $date ? $date->copy()->locale('uz')->isoFormat('dddd') : '',
                'color' => 'green',
                'pond_id' => $report->pond_id,
                'pond_name' => $report->pond?->name ?: ('#'.$report->pond_id),
                'weights' => $weights,
                'submitted_by' => $report->user?->name,
            ];
        })->all();
    }

    /**
     * Oxirgi 10 ta yo‘qotish (faqat sodir bo‘lganlar).
     *
     * @param  Collection<int, int|string>  $pondIds
     * @return list<array<string, mixed>>
     */
    private function buildLossCards(Collection $pondIds): array
    {
        $ids = $pondIds->all() ?: [-1];

        $ops = FishOperation::query()
            ->with(['pond:id,name', 'user:id,name'])
            ->whereIn('pond_id', $ids)
            ->where('type', 'loss')
            ->where('quantity', '>', 0)
            ->latest('operated_at')
            ->latest('id')
            ->limit(self::LOSSES_LIMIT)
            ->get();

        // Chapdan o‘ngga: eski → yangi
        return $ops->reverse()->values()->map(function (FishOperation $op) {
            $date = optional($op->operated_at);

            return [
                'id' => $op->id,
                'date' => $date?->toDateString(),
                'weekday' => $date ? $date->copy()->locale('uz')->isoFormat('dddd') : '',
                'color' => 'red',
                'pond_id' => $op->pond_id,
                'pond_name' => $op->pond?->name ?: ('#'.$op->pond_id),
                'fish_type' => (string) ($op->fish_type ?: ''),
                'quantity' => (int) $op->quantity,
                'total_weight_kg' => $op->total_weight_kg !== null ? (float) $op->total_weight_kg : null,
                'notes' => $op->notes,
                'submitted_by' => $op->user?->name,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, DailyReport>  $dayReports
     * @return array<string, mixed>
     */
    private function buildDayCard(Carbon $date, Collection $dayReports): array
    {
        $hasReport = $dayReports->isNotEmpty();
        $feedKg = 0.0;
        $feedNames = [];
        $medicines = [];
        $pondRows = [];

        foreach ($dayReports as $report) {
            $kg = (float) ($report->feeding_kg ?? 0) + (float) ($report->wheat_bran_kg ?? 0);
            $feedKg += $kg;
            if ($report->feed_name && $kg > 0) {
                $feedNames[] = (string) $report->feed_name;
            }

            $medLines = [];
            foreach ($report->items ?? [] as $item) {
                $wh = $item->warehouseItem;
                if (! $wh || $wh->category !== WarehouseCategories::MEDICINE) {
                    continue;
                }
                $qty = (float) ($item->quantity ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $line = [
                    'name' => (string) $wh->name,
                    'qty' => $qty,
                    'unit' => (string) ($wh->unit ?: ''),
                ];
                $medicines[] = $line;
                $medLines[] = $line;
            }

            $pondRows[] = [
                'pond_id' => $report->pond_id,
                'pond_name' => $report->pond?->name ?: ('#'.$report->pond_id),
                'feeding_kg' => round($kg, 2),
                'feed_name' => $report->feed_name,
                'medicines' => $medLines,
            ];
        }

        $feedNames = array_values(array_unique($feedNames));
        $hasFeed = $feedKg > 0;
        $hasMedicine = count($medicines) > 0;

        if (! $hasReport) {
            $color = 'red';
            $labelKey = 'companyInspectDayMissing';
        } elseif ($hasFeed && $hasMedicine) {
            $color = 'green';
            $labelKey = 'companyInspectDayBoth';
        } elseif ($hasFeed || $hasMedicine) {
            $color = 'yellow';
            $labelKey = $hasFeed ? 'companyInspectDayFeedOnly' : 'companyInspectDayMedicineOnly';
        } else {
            $color = 'red';
            $labelKey = 'companyInspectDayEmpty';
        }

        return [
            'date' => $date->toDateString(),
            'weekday' => $date->locale('uz')->isoFormat('dddd'),
            'color' => $color,
            'label_key' => $labelKey,
            'has_report' => $hasReport,
            'has_feed' => $hasFeed,
            'has_medicine' => $hasMedicine,
            'feed_kg' => round($feedKg, 2),
            'feed_names' => $feedNames,
            'medicines' => $medicines,
            'ponds' => $pondRows,
            'reports_count' => $dayReports->count(),
        ];
    }

    /** Legacy helper — plan interval (boshqa joylar uchun). */
    public function resolvePeriod(Company $company): array
    {
        $end = now()->startOfDay();
        $interval = PricingPlans::normalizeCheckInterval(
            $company->check_interval_days ?: PricingPlans::defaultCheckInterval((string) $company->plan)
        );
        $start = $end->copy()->subDays(max($interval - 1, 0));

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => $interval,
        ];
    }
}
