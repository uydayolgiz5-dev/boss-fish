<?php

namespace Tests\Feature;

use App\Models\Pond;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private function directorWithPond(): array
    {
        [$director, $farm, $pond] = $this->createDirectorWithFarmAndPond([
            'farm' => ['region' => 'Toshkent'],
            'pond' => ['region' => 'Toshkent'],
        ]);

        return [$director, $pond];
    }

    private function seedWarehouse(Pond $pond, User $director): array
    {
        return [
            'feed' => WarehouseItem::create([
                'farm_id' => $pond->farm_id,
                'category' => 'feed',
                'name' => 'Granula 35',
                'unit' => 'kg',
                'quantity' => 500,
                'created_by' => $director->id,
            ]),
            'bran' => WarehouseItem::create([
                'farm_id' => $pond->farm_id,
                'category' => 'feed',
                'name' => 'Kepak',
                'unit' => 'kg',
                'quantity' => 100,
                'created_by' => $director->id,
            ]),
            'fert' => WarehouseItem::create([
                'farm_id' => $pond->farm_id,
                'category' => 'medicine',
                'name' => 'Amafos',
                'unit' => 'kg',
                'quantity' => 50,
                'created_by' => $director->id,
            ]),
        ];
    }

    public function test_feed_types_list_warehouse_feeds_only(): void
    {
        [$director, $pond] = $this->directorWithPond();

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'unit' => 'kg',
            'quantity' => 100,
            'created_by' => $director->id,
        ]);

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'feed',
            'name' => 'Kepak',
            'unit' => 'kg',
            'quantity' => 50,
            'created_by' => $director->id,
        ]);

        $response = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/ponds/{$pond->id}/feed-types");

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Granula 35']);
        $response->assertJsonFragment(['name' => 'Kepak']);
    }

    public function test_feeding_kg_cannot_exceed_warehouse_stock(): void
    {
        [$director, $pond] = $this->directorWithPond();

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'unit' => 'kg',
            'quantity' => 50,
            'created_by' => $director->id,
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 80,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse']);
    }

    public function test_daily_report_saves_feed_bran_and_combined_fertilizer(): void
    {
        [$director, $pond] = $this->directorWithPond();
        $this->seedWarehouse($pond, $director);

        $response = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 120.5,
                'wheat_bran_kg' => 30,
                'fertilizer_kg' => 15.25,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('daily_reports', [
            'pond_id' => $pond->id,
            'feed_name' => 'Granula 35',
            'feeding_kg' => 120.50,
            'wheat_bran_kg' => 30.00,
            'fertilizer_kg' => 15.25,
        ]);

        $this->assertDatabaseHas('warehouse_items', [
            'name' => 'Granula 35',
            'quantity' => 379.5,
        ]);
    }

    public function test_custom_feed_name_requires_warehouse_stock(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
                'feed_name' => 'Maxsus yem 40',
                'feeding_kg' => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse']);
    }

    public function test_daily_report_can_consume_custom_medicine_from_warehouse(): void
    {
        [$director, $pond] = $this->directorWithPond();
        $this->seedWarehouse($pond, $director);

        $medicine = WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'unit' => 'kg',
            'quantity' => 20,
            'created_by' => $director->id,
        ]);

        $response = $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-30',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 10,
                'medicine_item_id' => $medicine->id,
                'medicine_qty' => 5,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $medicine->id,
            'quantity' => 15,
        ]);
    }

    public function test_warehouse_available_returns_grouped_stock(): void
    {
        [$director, $pond] = $this->directorWithPond();
        $stock = $this->seedWarehouse($pond, $director);

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'feed',
            'name' => "Bug'doy",
            'unit' => 'kg',
            'quantity' => 80,
            'created_by' => $director->id,
        ]);

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'unit' => 'kg',
            'quantity' => 12,
            'created_by' => $director->id,
        ]);

        $response = $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/ponds/{$pond->id}/warehouse/available");

        $response->assertOk();
        $names = collect($response->json('feed'))->pluck('name');
        $this->assertTrue($names->contains('Granula 35'));
        $this->assertTrue($names->contains('Kepak'));
        $this->assertTrue($names->contains("Bug'doy"));
        $this->assertSame([], $response->json('bran'));
        $this->assertSame([], $response->json('fertilizer'));
        $medicineNames = collect($response->json('medicines'))->pluck('name');
        $this->assertTrue($medicineNames->contains('Amafos'));
        $this->assertTrue($medicineNames->contains('Antibiotik X'));
    }

    public function test_empty_daily_report_is_rejected(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report']);
    }

    public function test_feeding_kg_requires_feed_name(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
                'feeding_kg' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report']);
    }

    public function test_medicine_only_daily_report_is_accepted(): void
    {
        [$director, $pond] = $this->directorWithPond();

        $medicine = WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'medicine',
            'name' => 'Antibiotik X',
            'unit' => 'kg',
            'quantity' => 12,
            'created_by' => $director->id,
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-29',
                'medicine_item_id' => $medicine->id,
                'medicine_qty' => 1.5,
            ])
            ->assertCreated()
            ->assertJsonPath('feed_name', null)
            ->assertJsonPath('feeding_kg', null);

        $this->assertEquals(10.5, (float) $medicine->fresh()->quantity);
    }

    public function test_named_feed_and_medicine_are_not_split_by_product_name(): void
    {
        [$director, $pond] = $this->directorWithPond();
        $this->seedWarehouse($pond, $director);

        WarehouseItem::create([
            'farm_id' => $pond->farm_id,
            'category' => 'feed',
            'name' => "Bug'doy",
            'unit' => 'kg',
            'quantity' => 80,
            'created_by' => $director->id,
        ]);

        $amafos = WarehouseItem::query()
            ->where('farm_id', $pond->farm_id)
            ->where('name', 'Amafos')
            ->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/ponds/{$pond->id}/daily-reports", [
                'report_date' => '2026-07-31',
                'feed_name' => "Bug'doy",
                'feeding_kg' => 20,
                'medicine_item_id' => $amafos->id,
                'medicine_qty' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('feed_name', "Bug'doy")
            ->assertJsonPath('feeding_kg', '20.00');

        $this->assertEquals(60.0, (float) WarehouseItem::query()->where('name', "Bug'doy")->value('quantity'));
        $this->assertEquals(47.0, (float) $amafos->fresh()->quantity);
    }
}
