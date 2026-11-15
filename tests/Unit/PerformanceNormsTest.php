<?php

namespace Tests\Unit;

use App\Support\PerformanceNorms;
use PHPUnit\Framework\TestCase;

class PerformanceNormsTest extends TestCase
{
    public function test_fcr_above_norm_only_when_over_max(): void
    {
        $this->assertFalse(PerformanceNorms::fcrAboveNorm(null));
        $this->assertFalse(PerformanceNorms::fcrAboveNorm(0));
        $this->assertFalse(PerformanceNorms::fcrAboveNorm(2.4));
        $this->assertTrue(PerformanceNorms::fcrAboveNorm(2.41));
        $this->assertTrue(PerformanceNorms::fcrAboveNorm(3.5));
    }

    public function test_survival_mins_match_species(): void
    {
        $this->assertSame(70.0, PerformanceNorms::survivalMin('sazan'));
        $this->assertSame(70.0, PerformanceNorms::survivalMin('tolstolob'));
        $this->assertSame(50.0, PerformanceNorms::survivalMin('oq_amur'));
        $this->assertSame(70.0, PerformanceNorms::survivalMin('targil_tolstolob'));
        $this->assertNull(PerformanceNorms::survivalMin('unknown'));
    }

    public function test_owner_visible_types_exclude_routine_reminders(): void
    {
        $this->assertTrue(PerformanceNorms::isOwnerVisible('fcr-high'));
        $this->assertTrue(PerformanceNorms::isOwnerVisible('feed-sharp-change'));
        $this->assertTrue(PerformanceNorms::isOwnerVisible('survival-low'));
        $this->assertTrue(PerformanceNorms::isOwnerVisible('water-quality'));
        $this->assertFalse(PerformanceNorms::isOwnerVisible('stock-low'));
        $this->assertFalse(PerformanceNorms::isOwnerVisible('stock-empty'));
        $this->assertFalse(PerformanceNorms::isOwnerVisible('feed-reminder'));
        $this->assertFalse(PerformanceNorms::isOwnerVisible('water-test-reminder'));
        $this->assertFalse(PerformanceNorms::isOwnerVisible('weigh-reminder'));
    }
}
