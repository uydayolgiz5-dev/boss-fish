<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FishOperation;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondFinanceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function setupFarm(): array
    {
        [$director, $farm, $pondA, $company] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Test farm Co'],
            'farm' => ['name' => 'Test farm', 'region' => 'Toshkent'],
            'pond' => ['name' => 'Havza A', 'region' => 'Toshkent', 'area_m2' => 1000],
            'director' => [
                'phone' => '+998901119900',
                'login' => 'dir_fin_'.uniqid(),
                'access_expires_at' => now()->addDays(300),
            ],
        ]);

        $pondB = \App\Models\Pond::withoutGlobalScopes()->create([
            'farm_id' => $farm->id,
            'company_id' => $company->id,
            'name' => 'Havza B',
            'region' => 'Toshkent',
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ]);

        return [$director, $farm, $pondA, $pondB];
    }

    public function test_closed_pond_rejects_new_expense(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();
        $pondA->update([
            'closed_at' => now(),
            'status' => 'inactive',
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'main',
                'title' => 'Yopilgan havza',
                'expense_date' => '2026-06-02',
                'amount' => 100000,
                'scope' => 'pond',
                'pond_id' => $pondA->id,
            ])
            ->assertStatus(422);
    }

    public function test_expense_can_be_scoped_to_pond_or_farm(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'salary',
                'title' => 'Ish haqi',
                'expense_date' => '2026-06-01',
                'amount' => 1000000,
                'scope' => 'farm',
            ])
            ->assertCreated()
            ->assertJsonPath('pond_id', null);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'main',
                'title' => 'Nasos',
                'expense_date' => '2026-06-02',
                'amount' => 200000,
                'scope' => 'pond',
                'pond_id' => $pondA->id,
            ])
            ->assertCreated()
            ->assertJsonPath('pond_id', $pondA->id);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(1200000.0, (float) $overview['total_expenses']);
        $this->assertCount(2, $overview['by_pond']);

        $pondOverview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $this->assertSame(200000.0, (float) $pondOverview['total_expenses']);
    }

    public function test_farm_wide_feed_allocates_by_usage(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();

        Expense::create([
            'farm_id' => $farm->id,
            'pond_id' => null,
            'user_id' => $director->id,
            'category' => 'feed',
            'amount' => 1000000,
            'quantity' => 1000,
            'unit_price' => 1000,
            'expense_date' => '2026-05-01',
            'title' => 'Yem',
        ]);

        DailyReport::create([
            'pond_id' => $pondA->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feeding_kg' => 70,
            'wheat_bran_kg' => 0,
        ]);

        DailyReport::create([
            'pond_id' => $pondB->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feeding_kg' => 30,
            'wheat_bran_kg' => 0,
        ]);

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();

        $this->assertSame(70000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(30000.0, (float) $overviewB['total_expenses']);
        $this->assertSame(70000.0, (float) $overviewA['feed_allocated']);
        $this->assertSame(30000.0, (float) $overviewB['feed_allocated']);

        $farmOverview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();
        $this->assertSame(1000000.0, (float) $farmOverview['total_expenses']);
    }

    public function test_unused_warehouse_feed_stays_off_pond_report(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();

        $this->warehouseIntake($director, $farm, [
            'category' => 'feed',
            'name' => 'Granula',
            'unit' => 'kg',
            'quantity' => 1000,
            'unit_price' => 1000,
        ]);

        DailyReport::create([
            'pond_id' => $pondA->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feed_name' => 'Granula',
            'feeding_kg' => 80,
            'wheat_bran_kg' => 0,
        ]);

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();
        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();

        $this->assertSame(80000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(0.0, (float) $overviewB['total_expenses']);

        $listA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/expenses?category=feed&pond_id={$pondA->id}")
            ->assertOk()
            ->json();
        $this->assertCount(1, $listA['data']);
        $this->assertTrue($listA['data'][0]['allocated_from_farm']);
        $this->assertSame(80.0, (float) $listA['data'][0]['quantity']);
        $this->assertSame(80000.0, (float) $listA['data'][0]['amount']);
        $this->assertSame(80000.0, (float) $listA['category_total']);

        $listB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/expenses?category=feed&pond_id={$pondB->id}")
            ->assertOk()
            ->json();
        $this->assertCount(0, $listB['data']);
        $this->assertSame(0.0, (float) $listB['category_total']);
    }

    public function test_farm_wide_medicine_allocates_by_usage(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();

        $this->warehouseIntake($director, $farm, [
            'category' => 'medicine',
            'name' => 'Antibiotik',
            'quantity' => 10,
            'unit_price' => 100000,
        ]);

        $item = \App\Models\WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('category', 'medicine')
            ->firstOrFail();

        $reportA = DailyReport::create([
            'pond_id' => $pondA->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-10',
            'feeding_kg' => 0,
        ]);
        \App\Models\DailyReportItem::create([
            'daily_report_id' => $reportA->id,
            'warehouse_item_id' => $item->id,
            'quantity' => 6,
        ]);
        $item->quantity = 4;
        $item->save();

        $reportB = DailyReport::create([
            'pond_id' => $pondB->id,
            'user_id' => $director->id,
            'report_date' => '2026-05-11',
            'feeding_kg' => 0,
        ]);
        \App\Models\DailyReportItem::create([
            'daily_report_id' => $reportB->id,
            'warehouse_item_id' => $item->id,
            'quantity' => 4,
        ]);
        $item->quantity = 0;
        $item->save();

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();

        $this->assertSame(600000.0, (float) $overviewA['medicine_allocated']);
        $this->assertSame(400000.0, (float) $overviewB['medicine_allocated']);
        $this->assertSame(600000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(400000.0, (float) $overviewB['total_expenses']);
    }

    public function test_farm_wide_equipment_allocates_equally_across_ponds(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();
        $pondB->update(['area_m2' => 3000]); // maydon farqi taqsimotga ta’sir qilmasligi kerak

        $this->warehouseIntake($director, $farm, [
            'category' => 'equipment',
            'name' => 'Transformator',
            'unit' => 'piece',
            'quantity' => 1,
            'unit_price' => 10000000,
        ]);

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();

        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();

        $farmOverview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(10000000.0, (float) $farmOverview['total_expenses']);
        $this->assertSame(5000000.0, (float) $overviewA['equipment_allocated']);
        $this->assertSame(5000000.0, (float) $overviewB['equipment_allocated']);
        $this->assertSame(5000000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(5000000.0, (float) $overviewB['total_expenses']);
    }

    public function test_pond_equipment_stays_on_that_pond_and_farm_total(): void
    {
        [$director, $farm, $pondA, $pondB] = $this->setupFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Karmushka',
                'unit' => 'piece',
                'quantity' => 1,
                'unit_price' => 3000000,
                'pond_id' => $pondA->id,
            ])
            ->assertCreated();

        $overviewA = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondA->id}")
            ->assertOk()
            ->json();
        $overviewB = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview?pond_id={$pondB->id}")
            ->assertOk()
            ->json();
        $farmOverview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(3000000.0, (float) $farmOverview['total_expenses']);
        $this->assertSame(3000000.0, (float) $overviewA['total_expenses']);
        $this->assertSame(0.0, (float) $overviewB['total_expenses']);
        $this->assertSame(0.0, (float) $overviewA['equipment_allocated']);
    }

    public function test_adding_fish_with_price_creates_pond_expense(): void
    {
        [$director, $farm, $pondA] = $this->setupFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pondA->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 1000,
                'average_weight_g' => 50,
                'unit_price' => 20000,
                'operated_at' => '2026-06-01',
                'title' => 'Baliq qo‘shish',
            ])
            ->assertCreated()
            ->assertJsonPath('operation.total_weight_kg', '50.000')
            ->assertJsonPath('operation.amount', '1000000.00');

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'pond_id' => $pondA->id,
            'category' => 'main',
            'amount' => 1000000,
        ]);

        $overview = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/finance/overview")
            ->assertOk()
            ->json();

        $this->assertSame(1000000.0, (float) $overview['total_expenses']);
    }

    public function test_add_fish_can_derive_average_from_total_mass(): void
    {
        [$director, , $pondA] = $this->setupFarm();

        $payload = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pondA->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 1000,
                'total_weight_kg' => 40,
                'operated_at' => '2026-06-01',
            ])
            ->assertCreated()
            ->json();

        $this->assertEqualsWithDelta(0.04, (float) $payload['operation']['average_weight_kg'], 0.0001);
    }
}
