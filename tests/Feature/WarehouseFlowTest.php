<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithPond(): array
    {
        [$director, $farm, $pond] = $this->createDirectorWithFarmAndPond([
            'director' => ['phone' => '+998901112233', 'login' => 'dir_whf_'.uniqid()],
        ]);

        return [$director, $farm, $pond];
    }

    private function addFeedViaFinance(User $director, Farm $farm, string $name, float $quantity): array
    {
        return $this->warehouseIntake($director, $farm, [
            'category' => 'feed',
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => 3500,
        ]);
    }

    private function addMedicineViaFinance(User $director, Farm $farm, string $name, float $quantity): array
    {
        return $this->warehouseIntake($director, $farm, [
            'category' => 'medicine',
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => 50000,
        ]);
    }

    public function test_finance_feed_adds_stock_and_daily_report_consumes_it(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $this->addFeedViaFinance($director, $farm, 'Granula 35', 200);
        $this->addMedicineViaFinance($director, $farm, 'Amafos', 50);

        $feed = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Granula 35')
            ->firstOrFail();

        $medicine = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Amafos')
            ->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-09',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 120.5,
                'fertilizer_kg' => 15,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $feed->id,
            'quantity' => 79.5,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $medicine->id,
            'quantity' => 35,
        ]);

        $this->assertDatabaseHas('daily_reports', [
            'pond_id' => $pond->id,
            'feeding_kg' => 120.5,
            'fertilizer_kg' => 15,
        ]);
    }

    public function test_consumable_intake_is_blocked(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'feed_name' => 'Maxsus yem 40',
                'feed_kg' => 25,
                'wheat_kg' => 10,
                'fertilizer_kg' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse/intake", [
                'medicine_name' => 'Antibiotik',
                'medicine_unit' => 'piece',
                'medicine_qty' => 12,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intake']);
    }

    public function test_intake_stores_expense_date_and_medicine_piece_unit(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'medicine',
                'name' => 'Antibiotik tabletkasi',
                'unit' => 'piece',
                'quantity' => 40,
                'unit_price' => 2500,
                'expense_date' => '2026-04-12',
            ])
            ->assertCreated()
            ->assertJsonPath('unit', 'piece')
            ->assertJsonPath('quantity', '40.00');

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'category' => 'medicine',
            'name' => 'Antibiotik tabletkasi',
            'unit' => 'piece',
            'quantity' => 40,
        ]);

        $expense = \App\Models\Expense::query()
            ->where('farm_id', $farm->id)
            ->where('title', 'Antibiotik tabletkasi')
            ->firstOrFail();
        $this->assertNull($expense->pond_id);
        $this->assertSame('2026-04-12', $expense->expense_date->toDateString());
        $this->assertEquals(100000, (float) $expense->amount);
    }

    public function test_director_can_delete_unused_warehouse_item_and_linked_expense(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $this->addFeedViaFinance($director, $farm, 'Granula 35', 200);

        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Granula 35')
            ->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/warehouse/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('warehouse_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('expenses', [
            'farm_id' => $farm->id,
            'title' => 'Granula 35',
        ]);
    }

    public function test_cannot_delete_warehouse_item_used_in_daily_report(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $this->addFeedViaFinance($director, $farm, 'Granula 35', 200);

        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'Granula 35')
            ->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-09',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 50,
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/director/farms/{$farm->id}/warehouse/{$item->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse']);

        $this->assertDatabaseHas('warehouse_items', ['id' => $item->id]);
    }

    public function test_equipment_can_be_assigned_to_a_pond(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Aerator',
                'unit' => 'piece',
                'quantity' => 1,
                'unit_price' => 8000000,
                'expense_date' => '2026-04-01',
                'pond_id' => $pond->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'category' => 'equipment',
            'title' => 'Aerator',
            'pond_id' => $pond->id,
            'amount' => 8000000,
        ]);
    }

    public function test_director_can_upload_photo_when_adding_warehouse_stock(): void
    {
        [$director, $farm] = $this->directorWithPond();

        $response = $this->actingAs($director, 'sanctum')
            ->post("/api/director/farms/{$farm->id}/warehouse", [
                'category' => 'equipment',
                'name' => 'Skuter',
                'unit' => 'piece',
                'quantity' => 1,
                'unit_price' => 1500000,
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('skuter.jpg'),
            ])
            ->assertCreated();

        $photoPath = $response->json('photo_url');
        $this->assertNotNull($photoPath);
        $this->assertStringContainsString('/storage/warehouse/', $photoPath);

        $this->assertDatabaseHas('warehouse_items', [
            'farm_id' => $farm->id,
            'name' => 'Skuter',
        ]);
    }

    public function test_worker_can_submit_daily_report_for_assigned_farm_pond(): void
    {
        [$director, $farm, $pond] = $this->directorWithPond();

        $role = \App\Models\Role::query()->create([
            'company_id' => $farm->company_id,
            'name' => 'Hodim: Yem',
            'permissions' => \App\Support\CompanyPermissions::permissionsForJobs(['feed_report']),
            'job_roles' => ['feed_report'],
            'is_active' => true,
        ]);

        $worker = User::create([
            'name' => 'Hodim',
            'phone' => '+998901110099',
            'password' => '1234',
            'password_plain' => '1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $farm->company_id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $worker->assignedFarms()->attach($farm->id);

        $this->addFeedViaFinance($director, $farm, "Bug'doy", 100);

        $item = WarehouseItem::query()
            ->where('farm_id', $farm->id)
            ->where('name', "Bug'doy")
            ->firstOrFail();

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/worker/me')
            ->assertOk()
            ->assertJsonPath('farm.id', $farm->id)
            ->assertJsonPath('pond.id', $pond->id);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/worker/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-08-09',
                'feed_name' => "Bug'doy",
                'feeding_kg' => 25,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'quantity' => 75,
        ]);
    }
}
