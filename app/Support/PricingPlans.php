<?php

namespace App\Support;

/**
 * Bosh sahifa (/) tariflari — kompaniya obunasining yagona manbasi.
 */
class PricingPlans
{
    public const PLAN_CABINET = 'cabinet';

    public const PLAN_CONTROL = 'control';

    public const PLAN_INDIVIDUAL = 'individual';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_YEAR = 'year';

    public const EXPIRING_SOON_DAYS = 10;

    /** Tekshiruv sanasiga shu kun qolganda ogohlantirish */
    public const CHECK_APPROACHING_DAYS = 3;

    /** Barcha tariflar uchun odatiy tekshiruv oralig‘i (kun) */
    public const DEFAULT_CHECK_INTERVAL = 7;

    public const MIN_CHECK_INTERVAL = 1;

    public const MAX_CHECK_INTERVAL = 90;

    public const PLANS = [
        self::PLAN_CABINET,
        self::PLAN_CONTROL,
        self::PLAN_INDIVIDUAL,
    ];

    public const PERIODS = [
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'id' => self::PLAN_CABINET,
                'name_key' => 'homePlanCabinetName',
                'summary_key' => 'homePlanCabinetSummary',
                'month_price' => 135_000,
                'year_price' => 1_299_000,
                'custom_price' => false,
            ],
            [
                'id' => self::PLAN_CONTROL,
                'name_key' => 'homePlanControlName',
                'summary_key' => 'homePlanControlSummary',
                'month_price' => 450_000,
                'year_price' => 4_320_000,
                'custom_price' => false,
            ],
            [
                'id' => self::PLAN_INDIVIDUAL,
                'name_key' => 'homePlanIndividualName',
                'summary_key' => 'homePlanIndividualSummary',
                'month_price' => null,
                'year_price' => null,
                'custom_price' => true,
            ],
        ];
    }

    public static function periodDays(string $period): int
    {
        return $period === self::PERIOD_MONTH ? 30 : 365;
    }

    public static function priceFor(string $plan, string $period): ?int
    {
        $row = collect(self::all())->firstWhere('id', $plan);
        if (! $row || ($row['custom_price'] ?? false)) {
            return null;
        }

        return $period === self::PERIOD_MONTH
            ? ($row['month_price'] ?? null)
            : ($row['year_price'] ?? null);
    }

    public static function isValidPlan(string $plan): bool
    {
        return in_array($plan, self::PLANS, true);
    }

    public static function isValidPeriod(string $period): bool
    {
        return in_array($period, self::PERIODS, true);
    }

    /** Barcha tariflarda muntazam tekshiruv talab qilinadi */
    public static function requiresChecks(?string $plan = null): bool
    {
        if ($plan === null || $plan === '') {
            return true;
        }

        return self::isValidPlan($plan);
    }

    public static function defaultCheckInterval(?string $plan = null): int
    {
        return self::DEFAULT_CHECK_INTERVAL;
    }

    public static function normalizeCheckInterval(mixed $days): int
    {
        $n = (int) $days;
        if ($n < self::MIN_CHECK_INTERVAL) {
            return self::DEFAULT_CHECK_INTERVAL;
        }

        return min(self::MAX_CHECK_INTERVAL, $n);
    }
}
