<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\FishOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_inspect_company_and_complete_check(): void
    {
        $sa = User::create([
            'name' => 'SA',
            'login' => 'sa_inspect',
            'email' => 'sa_inspect@test.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        [$admin, $farm, $pond, $company] = $this->createDirectorWithFarmAndPond([
            'company' => [
                'name' => 'Inspect Co',
                'is_active' => true,
                'plan' => 'control',
                'check_interval_days' => 7,
                'next_check_at' => now()->subDay()->toDateString(),
            ],
            'director' => ['login' => 'inspect_admin', 'phone' => '+998901110011'],
        ]);

        $this->assertTrue($company->fresh()->needsCheck());

        FishOperation::create([
            'pond_id' => $pond->id,
            'user_id' => $admin->id,
            'type' => 'loss',
            'fish_type' => 'sazan',
            'quantity' => 12,
            'operated_at' => now()->subDay()->toDateString(),
        ]);

        Expense::create([
            'farm_id' => $farm->id,
            'pond_id' => $pond->id,
            'user_id' => $admin->id,
            'category' => 'feed',
            'amount' => 500000,
            'expense_date' => now()->subDays(2)->toDateString(),
            'title' => 'Yem',
            'warehouse_item_id' => null,
        ]);
        Expense::create([
            'farm_id' => $farm->id,
            'pond_id' => $pond->id,
            'user_id' => $admin->id,
            'category' => 'other',
            'amount' => 50000,
            'expense_date' => now()->subDays(5)->toDateString(),
            'title' => 'Boshqa',
        ]);

        $inspection = $this->actingAs($sa, 'sanctum')
            ->getJson("/api/superadmin/companies/{$company->id}/inspection")
            ->assertOk()
            ->json();

        $this->assertSame($company->id, $inspection['company']['id']);
        $this->assertArrayHasKey('ponds', $inspection);
        $this->assertNotEmpty($inspection['ponds']);
        // Bitta ochiq havza — avtomatik tanlanadi
        $this->assertFalse($inspection['needs_pond']);
        $this->assertSame($pond->id, $inspection['pond']['id']);
        $this->assertArrayHasKey('days', $inspection);
        $this->assertArrayHasKey('counts', $inspection);
        $this->assertArrayHasKey('water_tests', $inspection);
        $this->assertArrayHasKey('controls', $inspection);
        $this->assertArrayHasKey('losses', $inspection);
        $this->assertArrayHasKey('finance', $inspection);
        $this->assertArrayHasKey('period', $inspection);
        $this->assertCount(10, $inspection['days']);
        $this->assertSame(10, $inspection['period']['days']);
        $this->assertIsArray($inspection['water_tests']);
        $this->assertIsArray($inspection['controls']);
        $this->assertIsArray($inspection['losses']);
        $this->assertIsArray($inspection['finance']);
        $this->assertLessThanOrEqual(5, count($inspection['water_tests']));
        $this->assertLessThanOrEqual(2, count($inspection['controls']));
        $this->assertLessThanOrEqual(10, count($inspection['losses']));
        $this->assertCount(1, $inspection['losses']);
        $this->assertSame('sazan', $inspection['losses'][0]['fish_type']);
        $this->assertSame(12, $inspection['losses'][0]['quantity']);
        $this->assertSame(550000.0, (float) $inspection['finance']['current_total']);
        $this->assertNotEmpty($inspection['finance']['top_categories']);
        $this->assertTrue(collect($inspection['finance']['flags'])->contains(fn ($f) => $f['type'] === 'misplaced'));
        $this->assertArrayHasKey('color', $inspection['days'][0]);
        $this->assertArrayHasKey('has_report', $inspection['days'][0]);
        $this->assertArrayHasKey('date', $inspection['days'][0]);

        $this->assertArrayHasKey('problem_alerts', $inspection);
        $this->assertIsArray($inspection['problem_alerts']);
        $this->assertLessThanOrEqual(10, count($inspection['problem_alerts']));
        foreach ($inspection['problem_alerts'] as $alert) {
            $this->assertArrayHasKey('type', $alert);
            $this->assertArrayHasKey('occurred_at', $alert);
            $this->assertNotContains($alert['type'], ['stock-low', 'stock-empty', 'feed-reminder', 'water-test-reminder', 'weigh-reminder']);
            $this->assertContains($alert['type'], ['fcr-high', 'feed-sharp-change', 'survival-low', 'water-quality']);
        }

        $byPond = $this->actingAs($sa, 'sanctum')
            ->getJson("/api/superadmin/companies/{$company->id}/inspection?pond_id={$pond->id}")
            ->assertOk()
            ->json();
        $this->assertSame($pond->id, $byPond['pond']['id']);
        $this->assertFalse($byPond['needs_pond']);
        $this->assertArrayHasKey('problem_alerts', $byPond);

        $checked = $this->actingAs($sa, 'sanctum')
            ->postJson("/api/superadmin/companies/{$company->id}/check", [
                'note' => 'Havzalar ko‘rib chiqildi',
            ])
            ->assertOk()
            ->json();

        $this->assertFalse($checked['needs_check']);
        $this->assertSame('Havzalar ko‘rib chiqildi', $checked['attention_note']);
        $this->assertFalse($company->fresh()->needsCheck());
        $this->assertNotNull($admin);
        $this->assertNotNull($farm);
        $this->assertNotNull($pond);
    }

    public function test_inspection_blocked_when_quiet_mode(): void
    {
        $sa = User::create([
            'name' => 'SA2',
            'login' => 'sa_inspect2',
            'email' => 'sa_inspect2@test.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        [, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => [
                'name' => 'Quiet Co',
                'is_active' => true,
                'quiet_mode' => true,
                'plan' => 'control',
                'check_interval_days' => 7,
                'next_check_at' => now()->subDay()->toDateString(),
            ],
            'director' => ['login' => 'quiet_admin', 'phone' => '+998901110022'],
        ]);

        $this->actingAs($sa, 'sanctum')
            ->getJson("/api/superadmin/companies/{$company->id}/inspection")
            ->assertStatus(422);
    }
}
