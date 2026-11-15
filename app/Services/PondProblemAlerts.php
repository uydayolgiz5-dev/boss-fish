<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\WarehouseItem;
use App\Support\FishTypes;
use App\Support\PerformanceNorms;
use Illuminate\Support\Collection;

/**
 * Muammo/holat ogohlantirishlari (ish eslatmasi emas).
 * Direktor + egasi tekshiruvi.
 */
class PondProblemAlerts
{
    /**
     * Direktor kabineti: FCR + yem keskin o‘zgarish + tiriklik.
     * Ombor pastligi topbarda alohida yuklanadi — shu yerda takrorlanmasin.
     *
     * @param  Collection<int, int>|array<int>  $farmIds
     * @return list<array<string, mixed>>
     */
    public static function forFarms($farmIds): array
    {
        $farmIds = collect($farmIds)->filter()->values();
        if ($farmIds->isEmpty()) {
            return [];
        }

        return array_values(array_merge(
            FcrNormAlerts::forFarms($farmIds),
            self::feedSharpForFarms($farmIds),
            self::survivalForFarms($farmIds),
        ));
    }

    /**
     * Kompaniya tekshiruvi: tanlangan havza + kompaniya ombori.
     * Oxirgi 10 ta muammo ogohlantirishi (sana/soat bilan).
     *
     * @return list<array<string, mixed>>
     */
    public static function forCompanyInspection(int $companyId, ?int $pondId = null): array
    {
        $farmIds = Farm::query()->where('company_id', $companyId)->pluck('id');
        if ($farmIds->isEmpty()) {
            return [];
        }

        $all = array_merge(
            self::forFarms($farmIds),
            self::waterQualityForFarms($farmIds),
        );

        if ($pondId) {
            $all = array_values(array_filter(
                $all,
                fn (array $a) => (int) ($a['pondId'] ?? 0) === (int) $pondId
            ));
        }

        $all = array_values(array_filter(
            $all,
            fn (array $a) => PerformanceNorms::isOwnerVisible((string) ($a['type'] ?? ''))
        ));

        usort($all, function (array $a, array $b) {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return array_slice($all, 0, 10);
    }

    /**
     * @param  Collection<int, int>  $farmIds
     * @return list<array<string, mixed>>
     */
    private static function feedSharpForFarms(Collection $farmIds): array
    {
        $ponds = self::openPonds($farmIds);
        if ($ponds->isEmpty()) {
            return [];
        }

        $alerts = [];
        $threshold = PerformanceNorms::FEED_SHARP_CHANGE_PCT;

        foreach ($ponds as $pond) {
            $rows = DailyReport::query()
                ->where('pond_id', $pond->id)
                ->orderByDesc('report_date')
                ->orderByDesc('id')
                ->limit(16)
                ->get(['report_date', 'feeding_kg', 'wheat_bran_kg', 'updated_at', 'created_at']);

            if ($rows->count() < 2) {
                continue;
            }

            // Chronological for pairwise compare (eski → yangi)
            $chrono = $rows->sortBy(fn ($r) => optional($r->report_date)->format('Y-m-d').'-'.$r->id)->values();
            for ($i = 1; $i < $chrono->count(); $i++) {
                $prev = $chrono[$i - 1];
                $curr = $chrono[$i];
                $currKg = round((float) $curr->feeding_kg + (float) $curr->wheat_bran_kg, 2);
                $prevKg = round((float) $prev->feeding_kg + (float) $prev->wheat_bran_kg, 2);
                if ($prevKg <= 0) {
                    continue;
                }

                $deltaPct = round((($currKg - $prevKg) / $prevKg) * 100, 1);
                if (abs($deltaPct) < $threshold) {
                    continue;
                }

                $day = optional($curr->report_date)->format('Y-m-d');
                // Yozilgan vaqt: created_at (hisobot kiritilgan payt), keyin updated_at
                $when = $curr->created_at ?: $curr->updated_at;
                $stamp = $when
                    ? $when->timezone('Asia/Tashkent')->toIso8601String()
                    : ($day ? $day.'T12:00:00+05:00' : now('Asia/Tashkent')->toIso8601String());

                $alerts[] = [
                    'type' => 'feed-sharp-change',
                    'tone' => 'warning',
                    'farmId' => $pond->farm_id,
                    'pondId' => $pond->id,
                    'farmName' => $pond->farm?->name,
                    'pondName' => $pond->name,
                    'delta_percent' => $deltaPct,
                    'message_key' => $deltaPct > 0 ? 'feedSharpChangeAlertUp' : 'feedSharpChangeAlertDown',
                    'message_params' => [
                        'pond' => $pond->name,
                        'percent' => number_format(abs($deltaPct), 0, '.', ''),
                    ],
                    'itemId' => 'feed-sharp-'.$pond->id.'-'.$day,
                    'route' => 'director-activities',
                    'occurred_at' => $stamp,
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, int>  $farmIds
     * @return list<array<string, mixed>>
     */
    private static function survivalForFarms(Collection $farmIds): array
    {
        $ponds = self::openPonds($farmIds);
        if ($ponds->isEmpty()) {
            return [];
        }

        $labels = FishTypes::labels();
        $alerts = [];

        foreach ($ponds as $pond) {
            foreach (PerformanceNorms::survivalMins() as $type => $minPct) {
                $added = (int) FishOperation::query()
                    ->where('pond_id', $pond->id)
                    ->where('type', 'add')
                    ->where('fish_type', $type)
                    ->sum('quantity');

                if ($added < 20) {
                    continue;
                }

                $lost = (int) FishOperation::query()
                    ->where('pond_id', $pond->id)
                    ->where('type', 'loss')
                    ->where('fish_type', $type)
                    ->sum('quantity');

                $alive = max(0, $added - $lost);
                $survival = round(($alive / $added) * 100, 1);
                if ($survival >= $minPct) {
                    continue;
                }

                $label = $labels[$type] ?? $type;
                $lastLossAt = FishOperation::query()
                    ->where('pond_id', $pond->id)
                    ->where('type', 'loss')
                    ->where('fish_type', $type)
                    ->orderByDesc('operated_at')
                    ->orderByDesc('id')
                    ->value('operated_at');
                $occurredAt = $lastLossAt
                    ? \Carbon\Carbon::parse($lastLossAt)->timezone('Asia/Tashkent')->toIso8601String()
                    : now('Asia/Tashkent')->toIso8601String();

                $alerts[] = [
                    'type' => 'survival-low',
                    'tone' => $survival < ($minPct - 15) ? 'danger' : 'warning',
                    'farmId' => $pond->farm_id,
                    'pondId' => $pond->id,
                    'farmName' => $pond->farm?->name,
                    'pondName' => $pond->name,
                    'fish_type' => $type,
                    'survival_percent' => $survival,
                    'norm_min' => $minPct,
                    'message_key' => 'survivalLowAlert',
                    'message_params' => [
                        'pond' => $pond->name,
                        'fish' => $label,
                        'percent' => number_format($survival, 1, '.', ''),
                        'min' => number_format($minPct, 0, '.', ''),
                    ],
                    'itemId' => 'survival-'.$pond->id.'-'.$type,
                    'route' => 'director-activities',
                    'occurred_at' => $occurredAt,
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, int>  $farmIds
     * @return list<array<string, mixed>>
     */
    private static function stockLowForFarms(Collection $farmIds): array
    {
        $items = WarehouseItem::query()
            ->with(['farm:id,name,company_id'])
            ->whereIn('farm_id', $farmIds)
            ->whereIn('category', ['feed', 'medicine'])
            ->where('quantity', '<=', PerformanceNorms::STOCK_LOW_QTY)
            ->whereHas('farm.company', fn ($q) => $q->where('is_active', true)->where('quiet_mode', false))
            ->get(['id', 'farm_id', 'name', 'category', 'quantity', 'updated_at']);

        $alerts = [];
        foreach ($items as $item) {
            $qty = (float) $item->quantity;
            $empty = $qty <= 0;
            $alerts[] = [
                'type' => $empty ? 'stock-empty' : 'stock-low',
                'tone' => $empty ? 'danger' : 'warning',
                'farmId' => $item->farm_id,
                'pondId' => null,
                'farmName' => $item->farm?->name,
                'pondName' => null,
                'message_key' => $empty ? 'directorEmptyStockAlert' : 'directorLowStockAlert',
                'message_params' => [
                    'name' => $item->name,
                    'qty' => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') ?: '0',
                ],
                'itemId' => $item->id,
                'route' => 'director-warehouse',
                'occurred_at' => optional($item->updated_at)?->timezone('Asia/Tashkent')?->toIso8601String()
                    ?: now('Asia/Tashkent')->toIso8601String(),
            ];
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, int>  $farmIds
     * @return list<array<string, mixed>>
     */
    private static function waterQualityForFarms(Collection $farmIds): array
    {
        // DirectorCabinetController::waterQualityAlerts bilan bir xil qisqa mezon
        $controllerAlerts = [];
        // Local lightweight: reuse WaterQualityStandards on recent reports
        $since = now()->subDays(30)->startOfDay();
        $reports = \App\Models\MainReport::query()
            ->with(['pond:id,name,farm_id,closed_at,current_stock', 'pond.farm:id,name'])
            ->whereHas('pond', function ($q) use ($farmIds) {
                $q->whereIn('farm_id', $farmIds)
                    ->whereNull('closed_at')
                    ->where('current_stock', '>', 0);
            })
            ->where('report_date', '>=', $since)
            ->latest('report_date')
            ->latest('id')
            ->limit(40)
            ->get();

        foreach ($reports as $report) {
            $assessment = WaterQualityStandards::evaluateReport($report);
            if (($assessment['level'] ?? 'ok') === WaterQualityStandards::LEVEL_OK) {
                continue;
            }
            if (empty($assessment['issues'])) {
                continue;
            }
            $pond = $report->pond;
            $day = optional($report->report_date)->format('Y-m-d');
            $time = null;
            if (is_string($report->test_time) && strlen($report->test_time) >= 5) {
                $time = substr($report->test_time, 0, 5);
            } elseif ($report->test_time) {
                try {
                    $time = \Carbon\Carbon::parse($report->test_time)->format('H:i');
                } catch (\Throwable $e) {
                    $time = null;
                }
            }
            if ($day && $time) {
                $occurredAt = $day.'T'.$time.':00+05:00';
            } else {
                $when = $report->created_at ?: $report->updated_at;
                $occurredAt = $when
                    ? $when->timezone('Asia/Tashkent')->toIso8601String()
                    : ($day ? $day.'T12:00:00+05:00' : null);
            }

            $controllerAlerts[] = [
                'type' => 'water-quality',
                'tone' => ($assessment['level'] === WaterQualityStandards::LEVEL_DANGER) ? 'danger' : 'warning',
                'level' => $assessment['level'],
                'farmId' => $pond?->farm_id,
                'pondId' => $pond?->id,
                'farmName' => $pond?->farm?->name,
                'pondName' => $pond?->name,
                'message_key' => $pond?->name ? 'waterAlertShortDetailPond' : 'waterAlertShortDetail',
                'message_params' => ['pond' => $pond?->name ?? ''],
                'itemId' => $report->id,
                'reportId' => $report->id,
                'route' => 'director-water-alert',
                'occurred_at' => $occurredAt ?: now('Asia/Tashkent')->toIso8601String(),
            ];
        }

        return array_slice($controllerAlerts, 0, 20);
    }

    /**
     * @param  Collection<int, int>  $farmIds
     * @return Collection<int, Pond>
     */
    private static function openPonds(Collection $farmIds): Collection
    {
        return Pond::query()
            ->with(['farm:id,name,company_id'])
            ->whereIn('farm_id', $farmIds)
            ->whereNull('closed_at')
            ->where('current_stock', '>', 0)
            ->whereHas('farm.company', fn ($q) => $q->where('is_active', true)->where('quiet_mode', false))
            ->get(['id', 'name', 'farm_id']);
    }
}
