<?php

namespace App\Support;

class FinanceCategories
{
    public const FEED = 'feed';

    public const MEDICINE = 'medicine';

    public const EQUIPMENT = 'equipment';

    public const SALARY = 'salary';

    public const MAIN = 'main';

    public const UTILITIES = 'utilities';

    public const CONTINGENT = 'contingent';

    public const OTHER = 'other';

    /** Moliyadan kiritiladigan doimiy bo‘limlar */
    public const ENTRY = [
        self::MAIN,
        self::SALARY,
        self::UTILITIES,
        self::CONTINGENT,
    ];

    /** @var list<string> */
    public const EXPENSE = [
        self::FEED,
        self::MEDICINE,
        self::EQUIPMENT,
        self::MAIN,
        self::SALARY,
        self::UTILITIES,
        self::CONTINGENT,
        self::OTHER,
    ];

    /** Ombordan kirim qilinadigan bo‘limlar (moliyadan kiritilmaydi) */
    public const WAREHOUSE_ORIGIN = [
        self::FEED,
        self::MEDICINE,
        self::EQUIPMENT,
    ];

    /** @var list<string> */
    public const WAREHOUSE_SYNC = [
        self::FEED,
        self::MEDICINE,
    ];

    public static function label(string $category): string
    {
        return match ($category) {
            self::FEED => 'Yem',
            self::MEDICINE => 'Dori-darmon',
            self::EQUIPMENT => 'Uskuna',
            self::MAIN => 'Asosiy xarajatlar',
            self::SALARY => 'Ish haqi',
            self::UTILITIES => 'Kommunal to‘lovlar',
            self::CONTINGENT => 'Ehtimoliy xarajatlar',
            self::OTHER => 'Boshqa xarajatlar',
            default => $category,
        };
    }

    public static function isFixed(string $category): bool
    {
        return in_array($category, self::EXPENSE, true);
    }

    public static function isCustom(string $category): bool
    {
        return (bool) preg_match('/^custom:\d+$/', $category);
    }

    public static function customId(string $category): ?int
    {
        if (! self::isCustom($category)) {
            return null;
        }

        $id = (int) substr($category, 7);

        return $id > 0 ? $id : null;
    }

    public static function customKey(int $sectionId): string
    {
        return 'custom:'.$sectionId;
    }

    public static function syncsToWarehouse(string $category): bool
    {
        return in_array($category, self::WAREHOUSE_SYNC, true);
    }

    public static function originatesFromWarehouse(string $category): bool
    {
        return in_array($category, self::WAREHOUSE_ORIGIN, true);
    }
}
