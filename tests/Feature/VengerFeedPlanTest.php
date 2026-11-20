<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VengerClient;
use App\Models\VengerFeedPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VengerFeedPlanTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::create([
            'name' => 'Boss',
            'email' => 'boss@bossfish.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_owner_can_create_year_plan_and_row_auto_creates_client(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/venger/feed-plans', ['year' => 2027])
            ->assertCreated()
            ->assertJsonPath('year', 2027);

        $plan = VengerFeedPlan::query()->first();
        $this->assertNotNull($plan);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$plan->id}/rows", [
                'client_name' => 'Akmal Karimov',
                'phone' => '+998901112233',
                'location_text' => 'Andijon, Asaka',
                'pond_area_ha' => 2.5,
                'last_year_feed_kg' => 12000,
                'rating_10' => 9,
                'feedback' => 'Yaxshi o‘sish',
                'next_year_carp_count' => 8000,
                'next_year_extruder_kg' => 15000,
                'next_year_granule_kg' => 3000,
            ])
            ->assertCreated()
            ->assertJsonPath('client_name', 'Akmal Karimov');

        $client = VengerClient::query()->where('phone', '+998901112233')->first();
        $this->assertNotNull($client);
        $this->assertSame('Akmal Karimov', $client->name);
        $this->assertSame('Andijon', $client->region);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$plan->id}/rows", [
                'client_name' => 'Akmal Karimov',
                'phone' => '+998901112233',
                'next_year_extruder_kg' => 100,
            ])
            ->assertCreated();

        $this->assertSame(1, VengerClient::query()->where('phone', '+998901112233')->count());

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/owner/venger/feed-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('totals.clients_count', 2)
            ->assertJsonPath('totals.next_year_extruder_kg', 15100);
    }

    public function test_existing_client_is_linked_without_duplicate(): void
    {
        $owner = $this->owner();
        $client = VengerClient::query()->create([
            'name' => 'Ali Valiyev',
            'first_name' => 'Ali',
            'last_name' => 'Valiyev',
            'phone' => '+998909998877',
            'region' => 'Samarqand',
            'district' => 'Samarqand shahri',
            'pond_area_ha' => 6.5,
            'created_by' => $owner->id,
        ]);

        $plan = VengerFeedPlan::query()->create([
            'year' => 2028,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$plan->id}/rows", [
                'client_name' => 'Boshqa Ism',
                'phone' => '+998909998877',
                'venger_client_id' => $client->id,
                'next_year_granule_kg' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('venger_client_id', $client->id)
            ->assertJsonPath('pond_area_ha', 6.5);

        $this->assertSame(1, VengerClient::query()->count());
        // Mijoz nomi reja qatoridan ustiga yozilmasligi kerak
        $this->assertSame('Ali Valiyev', $client->fresh()->name);
        $this->assertEquals(6.5, (float) $client->fresh()->pond_area_ha);
    }

    public function test_client_area_update_syncs_feed_plan_rows(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/venger/clients', [
                'first_name' => 'Akmal',
                'last_name' => 'Karimov',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'pond_area_ha' => 4,
                'phone' => '+998888889999',
            ])
            ->assertCreated();

        $client = VengerClient::query()->first();
        $plan = VengerFeedPlan::query()->create(['year' => 2026, 'created_by' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$plan->id}/rows", [
                'client_name' => 'Akmal Karimov',
                'phone' => '+998888889999',
                'venger_client_id' => $client->id,
                'next_year_extruder_kg' => 1000,
            ])
            ->assertCreated()
            ->assertJsonPath('pond_area_ha', 4);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/owner/venger/clients/{$client->id}", [
                'first_name' => 'Akmal',
                'last_name' => 'Karimov',
                'region' => 'Andijon',
                'district' => 'Andijon shahri',
                'pond_area_ha' => 6,
                'phone' => '+998888889999',
            ])
            ->assertOk()
            ->assertJsonPath('pond_area_ha', 6);

        $this->assertEquals(6.0, (float) \App\Models\VengerFeedPlanRow::query()->value('pond_area_ha'));

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/venger')
            ->assertOk()
            ->assertJsonPath('stats.area_ha', 6);
    }

    public function test_year_comparison_shows_growth_percent(): void
    {
        $owner = $this->owner();

        $y26 = VengerFeedPlan::query()->create(['year' => 2026, 'created_by' => $owner->id]);
        $y27 = VengerFeedPlan::query()->create(['year' => 2027, 'created_by' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$y26->id}/rows", [
                'client_name' => 'A',
                'phone' => '+998901110001',
                'next_year_extruder_kg' => 100,
                'next_year_granule_kg' => 50,
            ])->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/venger/feed-plans/{$y27->id}/rows", [
                'client_name' => 'B',
                'phone' => '+998901110002',
                'phone2' => '+998901110003',
                'next_year_extruder_kg' => 150,
                'next_year_granule_kg' => 50,
            ])->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/venger/feed-plans')
            ->assertOk()
            ->assertJsonPath('comparison.0.year', 2027)
            ->assertJsonPath('comparison.0.growth.next_year_extruder_kg', 50)
            ->assertJsonPath('comparison.0.growth.total_feed_plan_t', 33.3);
    }
}
