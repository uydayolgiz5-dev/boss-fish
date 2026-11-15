<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWarehouseSyncTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithFarm(): array
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => '7 Gektar Co'],
            'farm' => ['name' => '7 Gektar', 'region' => 'Sirdaryo'],
            'pond' => ['name' => 'Havza 1', 'area_m2' => 70000],
            'director' => ['phone' => '+998901115566', 'login' => 'dir_wh_'.uniqid()],
        ]);

        return [$director, $farm];
    }

    public function test_feed_expense_adds_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->warehouseIntake($director, $farm, [
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 500,
            'unit_price' => 3500,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 500,
            'unit_price' => 3500,
            'amount' => 1750000,
        ]);

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'title' => 'Granula 35',
            'amount' => 1750000,
        ]);

        $this->assertDatabaseHas('feed_types', [
            'farm_id' => $farm->id,
            'name' => 'Granula 35',
        ]);
    }

    public function test_medicine_expense_adds_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->warehouseIntake($director, $farm, [
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'quantity' => 20,
            'unit_price' => 50000,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'quantity' => 20,
            'unit_price' => 50000,
            'amount' => 1000000,
        ]);

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'category' => 'medicine',
            'title' => 'Antibiotik X',
            'amount' => 1000000,
        ]);
    }

    public function test_other_expense_does_not_add_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                'category' => 'other',
                'title' => 'Kol ijarasi',
                'expense_date' => '2026-08-24',
                'quantity' => 1,
                'unit_price' => 1000000,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('warehouse_items', 0);
    }

    public function test_finance_cannot_delete_warehouse_feed_expense(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $item = $this->warehouseIntake($director, $farm, [
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 100,
            'unit_price' => 3500,
        ]);

        $expenseId = Expense::query()
            ->where('warehouse_item_id', $item['id'])
            ->value('id');

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/finance/expenses/{$expenseId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'name' => 'Granula 35',
            'quantity' => 100,
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'category' => 'feed',
        ]);
    }

    public function test_consumable_warehouse_intake_is_blocked(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'feed_name' => 'Granula 35',
                'feed_kg' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);

        $this->warehouseIntake($director, $farm, [
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 100,
            'unit_price' => 3500,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'quantity' => 100,
        ]);
        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'title' => 'Granula 35',
        ]);
    }

    public function test_equipment_can_be_issued_from_warehouse(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $item = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Skuter',
                'unit' => 'piece',
                'quantity' => 2,
                'unit_price' => 1500000,
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/{$item['id']}/issue", [
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('quantity', '1.00');
    }

    public function test_finance_rejects_feed_medicine_equipment_categories(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        foreach (['feed', 'medicine', 'equipment'] as $category) {
            $this->actingAs($director, 'sanctum')
                ->postJson("/api/director/farms/{$farm->id}/finance/expenses", [
                    'category' => $category,
                    'title' => 'Test '.$category,
                    'expense_date' => '2026-08-24',
                    'quantity' => 10,
                    'unit_price' => 1000,
                ])
                ->assertStatus(422);
        }

        $this->assertDatabaseMissing('expenses', [
            'farm_id' => $farm->id,
            'category' => 'feed',
            'title' => 'Test feed',
        ]);
    }

    public function test_equipment_intake_creates_expense(): void
    {
        [$director, $farm] = $this->directorWithFarm();

        $this->warehouseIntake($director, $farm, [
            'category' => 'equipment',
            'name' => 'Aerator',
            'unit' => 'piece',
            'quantity' => 2,
            'unit_price' => 1500000,
        ]);

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'category' => 'equipment',
            'title' => 'Aerator',
            'amount' => 3000000,
        ]);
    }
}
