<?php

namespace Tests\Feature;

use App\Models\FishOperation;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerFullAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $director;

    private $pond;

    private $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->makeSuperadmin([
            'email' => 'ega@baliq.uz',
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
        ]);

        [$this->director, $this->farm, $this->pond] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Direktor',
                'phone' => '+998901112266',
                'login' => 'dirfull',
                'password' => 'dir1234',
                'password_plain' => 'dir1234',
            ],
            'farm' => [
                'name' => 'Test hojalik',
                'region' => 'Toshkent',
                'created_by' => null,
            ],
            'pond' => [
                'name' => 'Test havza',
                'region' => 'Toshkent',
            ],
        ]);
    }

    public function test_owner_can_list_all_ponds_and_open_director_routes(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->company_id)
            ->getJson('/api/director/ponds')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->pond->id]);

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->company_id)
            ->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertOk();
    }

    public function test_owner_can_create_daily_report_as_acting_company(): void
    {
        $item = WarehouseItem::create([
            'farm_id' => $this->farm->id,
            'company_id' => $this->farm->company_id,
            'category' => 'feed',
            'name' => 'Granula 35',
            'unit' => 'kg',
            'quantity' => 500,
            'created_by' => $this->director->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->company_id)
            ->postJson("/api/director/ponds/{$this->pond->id}/daily-reports", [
                'report_date' => '2026-07-01',
                'feed_name' => 'Granula 35',
                'feeding_kg' => 15.5,
                'feed_warehouse_item_id' => $item->id,
            ])
            ->assertCreated()
            ->assertJsonPath('feeding_kg', '15.50');

        $this->assertDatabaseHas('daily_reports', [
            'pond_id' => $this->pond->id,
            'feeding_kg' => 15.5,
        ]);
    }

    public function test_owner_can_update_fish_add_and_edit(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->company_id)
            ->postJson("/api/director/ponds/{$this->pond->id}/fish-operations", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 100,
                'average_weight_g' => 200,
                'operated_at' => '2026-07-01',
            ])
            ->assertCreated();

        $operation = FishOperation::first();

        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->director->company_id)
            ->putJson("/api/director/ponds/{$this->pond->id}/fish-operations/{$operation->id}", [
                'type' => 'add',
                'fish_type' => 'sazan',
                'quantity' => 120,
                'average_weight_g' => 200,
                'operated_at' => '2026-07-01',
            ])
            ->assertOk();
    }

    public function test_director_cannot_access_another_company_pond(): void
    {
        [$other] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Other Co'],
            'director' => [
                'name' => 'Other',
                'phone' => '+998909998877',
                'login' => 'otherdir',
            ],
        ]);

        // CompanyScope: boshqa kompaniya havzasi — 404
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pond->id}/summary")
            ->assertNotFound();
    }
}
