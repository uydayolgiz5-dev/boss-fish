<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Support\PricingPlans;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPlanStackTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_plan_stacks_on_remaining_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00:00'));

        $company = Company::create([
            'name' => 'Test Co',
            'is_active' => true,
            'plan' => PricingPlans::PLAN_CABINET,
            'billing_period' => PricingPlans::PERIOD_MONTH,
            'plan_starts_at' => now()->subDays(10),
            'access_expires_at' => now()->addDays(20),
            'check_interval_days' => 7,
        ]);

        $before = $company->access_expires_at->copy();
        $company->applyPlan(PricingPlans::PLAN_CABINET, PricingPlans::PERIOD_MONTH, true);
        $company->save();

        $this->assertTrue(
            $company->fresh()->access_expires_at->equalTo($before->copy()->addDays(30))
        );
    }

    public function test_apply_plan_starts_from_now_when_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00:00'));

        $company = Company::create([
            'name' => 'Expired Co',
            'is_active' => false,
            'plan' => PricingPlans::PLAN_CABINET,
            'billing_period' => PricingPlans::PERIOD_MONTH,
            'plan_starts_at' => now()->subDays(40),
            'access_expires_at' => now()->subDays(5),
            'check_interval_days' => 7,
        ]);

        $company->applyPlan(PricingPlans::PLAN_CONTROL, PricingPlans::PERIOD_YEAR, true);
        $company->save();
        $fresh = $company->fresh();

        $this->assertTrue($fresh->is_active);
        $this->assertTrue($fresh->access_expires_at->equalTo(now()->addDays(365)));
        $this->assertSame(PricingPlans::PLAN_CONTROL, $fresh->plan);
        $this->assertSame(PricingPlans::PERIOD_YEAR, $fresh->billing_period);
    }
}
