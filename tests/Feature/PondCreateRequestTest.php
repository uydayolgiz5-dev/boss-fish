<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\PondCreateRequest;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondCreateRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
    }

    public function test_director_submits_pond_create_request_instead_of_creating_pond(): void
    {
        [$director, $farm] = array_slice($this->createDirectorWithFarmAndPond(), 0, 2);
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/ponds", [
                'name' => 'Yangi havza',
                'region' => 'Toshkent viloyati',
                'district' => 'Qibray tumani',
                'area_m2' => 8.5,
            ])
            ->assertCreated()
            ->assertJsonPath('request.status', PondCreateRequest::STATUS_PENDING)
            ->assertJsonPath('request.name', 'Yangi havza');

        $this->assertDatabaseMissing('ponds', [
            'farm_id' => $farm->id,
            'name' => 'Yangi havza',
        ]);

        $this->assertDatabaseHas('pond_create_requests', [
            'farm_id' => $farm->id,
            'requested_by' => $director->id,
            'name' => 'Yangi havza',
            'status' => PondCreateRequest::STATUS_PENDING,
        ]);

        $request = PondCreateRequest::query()->where('name', 'Yangi havza')->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/pond-create-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('pond.name', 'Yangi havza')
            ->assertJsonPath('pond.district', 'Qibray tumani');

        $this->assertDatabaseHas('ponds', [
            'farm_id' => $farm->id,
            'company_id' => $farm->company_id,
            'name' => 'Yangi havza',
            'district' => 'Qibray tumani',
            'area_m2' => 8.5,
            'status' => 'active',
        ]);

        $this->actingAs($director, 'sanctum')
            ->getJson("/api/director/farms/{$farm->id}/ponds")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Yangi havza']);

        $request->refresh();
        $this->assertSame(PondCreateRequest::STATUS_APPROVED, $request->status);
        $this->assertNotNull($request->pond_id);
    }

    public function test_owner_requests_summary_counts_pending_create_pond(): void
    {
        [$director, $farm] = array_slice($this->createDirectorWithFarmAndPond(), 0, 2);
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/ponds", [
                'name' => 'Kutilayotgan havza',
                'region' => 'Toshkent viloyati',
                'district' => 'Qibray tumani',
                'area_m2' => 4,
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/requests-summary')
            ->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('create_pond', 1)
            ->assertJsonPath('closure', 0);
    }

    public function test_owner_acting_as_director_creates_pond_directly(): void
    {
        $owner = $this->createOwner();
        [$director, $farm] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Direktor',
                'phone' => '+998901112233',
                'login' => 'dirpond',
                'password' => '1234',
                'password_plain' => '1234',
            ],
            'farm' => [
                'name' => 'Test farm',
                'region' => 'Toshkent viloyati',
                'created_by' => $owner->id,
            ],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeaders(['X-Acting-Director-Id' => (string) $director->company_id])
            ->postJson("/api/director/farms/{$farm->id}/ponds", [
                'name' => 'BOSS havza',
                'region' => 'Toshkent viloyati',
                'district' => 'Qibray tumani',
                'area_m2' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'BOSS havza');

        $this->assertDatabaseHas('ponds', [
            'farm_id' => $farm->id,
            'name' => 'BOSS havza',
        ]);
        $this->assertDatabaseCount('pond_create_requests', 0);
    }

    public function test_support_message_can_be_sent_and_listed_for_owner(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond();
        $owner = $this->createOwner();

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/support/messages', [
                'message' => 'Havza qo‘shishda xato chiqyapti',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('support_messages', [
            'user_id' => $director->id,
            'user_role' => 'director',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/support-messages')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('messages.0.message', 'Havza qo‘shishda xato chiqyapti');

        $message = SupportMessage::query()->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/owner/support-messages/{$message->id}/read")
            ->assertOk();

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_guest_can_send_support_message_without_login(): void
    {
        $this->postJson('/api/support/messages', [
            'name' => 'Ali Mehmon',
            'phone' => '+998901234567',
            'message' => 'Saytdan qanday foydalanishni bilmayapman',
        ])
            ->assertCreated();

        $this->assertDatabaseHas('support_messages', [
            'user_id' => null,
            'user_role' => 'guest',
            'user_name' => 'Ali Mehmon',
            'user_phone' => '+998901234567',
        ]);
    }
}
