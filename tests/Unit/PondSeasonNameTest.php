<?php

namespace Tests\Unit;

use App\Support\PondSeasonName;
use PHPUnit\Framework\TestCase;

class PondSeasonNameTest extends TestCase
{
    public function test_base_name_strips_year_suffixes(): void
    {
        $this->assertSame('Katta kol', PondSeasonName::baseName('Katta kol 2026'));
        $this->assertSame('Katta kol', PondSeasonName::baseName('Katta kol 2025/2026'));
        $this->assertSame('Havza 1', PondSeasonName::baseName('Havza 1 · 2026'));
        $this->assertSame('Havza 1', PondSeasonName::baseName('Havza 1'));
    }

    public function test_with_season_years_single_and_range(): void
    {
        $this->assertSame(
            'Katta kol 2026',
            PondSeasonName::withSeasonYears('Katta kol', '2026-03-01', '2026-10-01')
        );
        $this->assertSame(
            'Katta kol 2025/2026',
            PondSeasonName::withSeasonYears('Katta kol', '2025-11-01', '2026-03-01')
        );
        $this->assertSame(
            'Katta kol 2026',
            PondSeasonName::withSeasonYears('Katta kol 2025', '2026-01-01', '2026-08-01')
        );
    }
}
