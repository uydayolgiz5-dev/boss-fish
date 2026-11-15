<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\FishOperation;
use App\Models\MainReport;
use App\Models\Pond;
use App\Support\PerformanceNorms;
use Illuminate\Support\Collection;

/**
 * Sazan FCR me’yordan oshsa — yumshoq ogohlantirish (kirish bloklanmaydi).
 */
class FcrNormAlerts
{
    /**
     * @param  Collection<int, int>|array<int>  $farmIds
     * @return list<array<string, mixed>>
     */
    public static function forFarms($farmIds): array
    {
        $farmIds = collect($farmIds)->filter()->values();
        if ($farmIds->isEmpty()) {
            return [];
        }

        $ponds = Pond::query()
            ->with(['farm:id,name,company_id'])
            ->whereIn('farm_id', $farmIds)
            ->whereNull('closed_at')
            ->where('current_stock', '>', 0)
            ->whereHas('farm.company', fn ($q) => $q->where('is_active', true)->where('quiet_mode', false))
            ->get(['id', 'name', 'farm_id']);

        if ($ponds->isEmpty()) {
            return [];
        }

        $alerts = [];
        foreach ($ponds as $pond) {
            $fcr = self::sazanFcrForPond((int) $pond->id);
            if (! PerformanceNorms::fcrAboveNorm($fcr)) {
                continue;
            }

            $occurredAt = self::latestSazanWeightOccurredAt((int) $pond->id)
                ?: now('Asia/Tashkent')->toIso8601String();

            $alerts[] = [
                'type' => 'fcr-high',
                'tone' => 'warning',
                'farmId' => $pond->farm_id,
                'pondId' => $pond->id,
                'farmName' => $pond->farm?->name,
                'pondName' => $pond->name,
                'fcr' => $fcr,
                'norm_max' => PerformanceNorms::FCR_MAX,
                'message_key' => 'fcrNormAlert',
                'message_params' => [
                    'pond' => $pond->name,
                    'fcr' => number_format((float) $fcr, 2, '.', ''),
                    'max' => number_format(PerformanceNorms::FCR_MAX, 1, '.', ''),
                ],
                'itemId' => 'fcr-'.$pond->id,
                'route' => 'director-activities',
                'occurred_at' => $occurredAt,
            ];
        }

        return $alerts;
    }

    public static function sazanFcrForPond(int $pondId): ?float
    {
        $count = self::sazanCount($pondId);
        if ($count <= 0) {
            return null;
        }

        $current = self::latestSazanWeightKg($pondId);
        $initial = self::initialSazanWeightKg($pondId);
        if ($current === null || $initial === null || $current <= $initial) {
            return null;
        }

        $sourceDate = self::latestSazanWeightDate($pondId);
        if (! $sourceDate) {
            return null;
        }

        $feedKg = self::feedTotalUntil($pondId, $sourceDate);
        $gainPerFishKg = $current - $initial;
        $biomassGainKg = round($gainPerFishKg * $count, 2);
        if ($biomassGainKg <= 0 || $feedKg <= 0) {
            return null;
        }

        return round($feedKg / $biomassGainKg, 2);
    }

    private static function sazanCount(int $pondId): int
    {
        $added = (int) FishOperation::query()
            ->where('pond_id', $pondId)
            ->where('type', 'add')
            ->where('fish_type', 'sazan')
            ->sum('quantity');

        $removed = (int) FishOperation::query()
            ->where('pond_id', $pondId)
            ->whereIn('type', ['sale', 'loss'])
            ->where('fish_type', 'sazan')
            ->sum('quantity');

        return max(0, $added - $removed);
    }

    private static function latestSazanWeightKg(int $pondId): ?float
    {
        $report = MainReport::query()
            ->where('pond_id', $pondId)
            ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', 'sazan'))
            ->with(['fishWeights' => fn ($q) => $q->where('fish_type', 'sazan')])
            ->latest('report_date')
            ->first();

        if (! $report || $report->fishWeights->isEmpty()) {
            return null;
        }

        return (float) $report->fishWeights->first()->average_weight_kg;
    }

    private static function latestSazanWeightDate(int $pondId): ?string
    {
        $date = MainReport::query()
            ->where('pond_id', $pondId)
            ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', 'sazan'))
            ->latest('report_date')
            ->value('report_date');

        return $date ? (string) $date : null;
    }

    /** Ogohlantirish sanasi/soati: test_time yoki hisobot yozilgan vaqt */
    private static function latestSazanWeightOccurredAt(int $pondId): ?string
    {
        $report = MainReport::query()
            ->where('pond_id', $pondId)
            ->whereHas('fishWeights', fn ($q) => $q->where('fish_type', 'sazan'))
            ->latest('report_date')
            ->latest('id')
            ->first(['report_date', 'test_time', 'created_at', 'updated_at']);

        if (! $report) {
            return null;
        }

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
            return $day.'T'.$time.':00+05:00';
        }

        $when = $report->created_at ?: $report->updated_at;
        if ($when) {
            return $when->timezone('Asia/Tashkent')->toIso8601String();
        }

        return $day ? $day.'T12:00:00+05:00' : null;
    }

    private static function initialSazanWeightKg(int $pondId): ?float
    {
        $operations = FishOperation::query()
            ->where('pond_id', $pondId)
            ->where('type', 'add')
            ->where('fish_type', 'sazan')
            ->whereNotNull('average_weight_kg')
            ->where('quantity', '>', 0)
            ->orderBy('operated_at')
            ->get(['quantity', 'average_weight_kg']);

        if ($operations->isEmpty()) {
            return null;
        }

        $totalQty = (int) $operations->sum('quantity');
        if ($totalQty <= 0) {
            return null;
        }

        $totalMassKg = $operations->sum(
            fn ($op) => ((int) $op->quantity) * (float) $op->average_weight_kg
        );

        return round($totalMassKg / $totalQty, 4);
    }

    private static function feedTotalUntil(int $pondId, string $date): float
    {
        $row = DailyReport::query()
            ->where('pond_id', $pondId)
            ->whereDate('report_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(feeding_kg), 0) as feed_kg, COALESCE(SUM(wheat_bran_kg), 0) as bran_kg')
            ->first();

        return round((float) ($row->feed_kg ?? 0) + (float) ($row->bran_kg ?? 0), 2);
    }
}
