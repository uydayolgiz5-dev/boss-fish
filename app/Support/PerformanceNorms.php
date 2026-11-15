<?php

namespace App\Support;

/**
 * Ishlash me’yorlari — bloklamaydi, faqat ogohlantirish.
 */
class PerformanceNorms
{
    /** Sazan FCR: 0 … FCR_MAX (shu oraliq norma). */
    public const FCR_MIN = 0.0;

    public const FCR_MAX = 2.4;

    /** Kunlik yem oldingi kunga nisbatan shuncha % o‘zgarsa — keskin. */
    public const FEED_SHARP_CHANGE_PCT = 40.0;

    /** Ombor (yem/dori) past qoldiq chegarasi. */
    public const STOCK_LOW_QTY = 10.0;

    /**
     * Tiriklik (survival) minimal foizi — yo‘qotishdan keyin.
     * Tiriklik = (qo‘shilgan − yo‘qotilgan) / qo‘shilgan × 100.
     *
     * @return array<string, float>
     */
    public static function survivalMins(): array
    {
        return [
            'sazan' => 70.0,
            'tolstolob' => 70.0,
            'oq_amur' => 50.0,
            'targil_tolstolob' => 70.0,
        ];
    }

    public static function survivalMin(string $fishType): ?float
    {
        return self::survivalMins()[$fishType] ?? null;
    }

    public static function fcrAboveNorm(?float $fcr): bool
    {
        if ($fcr === null) {
            return false;
        }

        return $fcr > self::FCR_MAX;
    }

    /**
     * Egasi/tekshiruv ko‘radigan muammo ogohlantirishlari.
     * Faqat norma/buzilishlar — ombor (mijoz kabineti) va kunlik ish eslatmalari yo‘q.
     *
     * @var list<string>
     */
    public const OWNER_ALERT_TYPES = [
        'fcr-high',
        'feed-sharp-change',
        'survival-low',
        'water-quality',
    ];

    public static function isOwnerVisible(string $type): bool
    {
        return in_array($type, self::OWNER_ALERT_TYPES, true);
    }
}
