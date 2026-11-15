<?php

namespace App\Support;

use App\Models\FishOperation;
use App\Models\Pond;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PondSeasonName
{
    /**
     * Yil qo‘shimchasini olib tashlaydi: "Katta kol 2026", "Katta kol 2026/2027", "Havza 1 · 2026".
     */
    public static function baseName(?string $name): string
    {
        $raw = trim((string) $name);
        if ($raw === '') {
            return '';
        }

        $stripped = preg_replace(
            '/\s*[·\-]?\s*\(?20\d{2}(?:\s*\/\s*20\d{2})?\)?\s*$/u',
            '',
            $raw
        );

        $base = trim((string) $stripped);

        return $base !== '' ? $base : $raw;
    }

    /**
     * @return list<int>
     */
    public static function yearsForSeason(
        CarbonInterface|string|null $startedOn,
        CarbonInterface|string $closedOn
    ): array {
        $end = Carbon::parse($closedOn)->year;
        $start = $startedOn
            ? Carbon::parse($startedOn)->year
            : $end;

        if ($start > $end) {
            $start = $end;
        }

        if ($start === $end) {
            return [$end];
        }

        return [$start, $end];
    }

    /**
     * @param  list<int>  $years
     */
    public static function formatYears(array $years): string
    {
        $years = array_values(array_unique(array_map('intval', $years)));
        sort($years);

        if ($years === []) {
            return (string) now()->year;
        }

        if (count($years) === 1) {
            return (string) $years[0];
        }

        return $years[0].'/'.$years[count($years) - 1];
    }

    public static function withSeasonYears(
        ?string $name,
        CarbonInterface|string|null $startedOn,
        CarbonInterface|string $closedOn
    ): string {
        $base = self::baseName($name);
        $years = self::formatYears(self::yearsForSeason($startedOn, $closedOn));

        return trim($base.' '.$years);
    }

    /**
     * Mavsum boshlanishi: birinchi baliq qo‘shish sanasi, yo‘q bo‘lsa havza yaratilgan sana.
     */
    public static function seasonStartedOn(Pond $pond, CarbonInterface|string $closedOn): ?string
    {
        $closedDate = Carbon::parse($closedOn)->toDateString();

        $firstAdd = FishOperation::query()
            ->where('pond_id', $pond->id)
            ->where('type', 'add')
            ->whereDate('operated_at', '<=', $closedDate)
            ->orderBy('operated_at')
            ->value('operated_at');

        if ($firstAdd) {
            return Carbon::parse($firstAdd)->toDateString();
        }

        if ($pond->created_at) {
            return $pond->created_at->toDateString();
        }

        return null;
    }

    public static function applyOnClose(Pond $pond, CarbonInterface|string $closedOn): string
    {
        $startedOn = self::seasonStartedOn($pond, $closedOn);
        $newName = self::withSeasonYears($pond->name, $startedOn, $closedOn);
        $pond->name = $newName;

        return $newName;
    }
}
