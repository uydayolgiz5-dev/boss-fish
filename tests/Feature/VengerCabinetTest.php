<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VengerCabinetTest extends TestCase
{
    use RefreshDatabase;

    public function test_venger_staff_can_login_via_hidden_endpoint(): void
    {
        $user = User::create([
            'name' => 'Venger Staff',
            'email' => 'hodim@vengercarp.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::ROLE_VENGER,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/venger-login', [
            'login' => 'hodim@vengercarp.uz',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'venger');

        $this->postJson('/api/auth/login', [
            'phone' => '+998901112233',
            'password' => 'secret123',
        ])->assertUnprocessable();
    }

    public function test_venger_dashboard_requires_venger_role(): void
    {
        $venger = User::create([
            'name' => 'Venger Staff',
            'email' => 'hodim@vengercarp.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::ROLE_VENGER,
            'is_active' => true,
        ]);

        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($venger, 'sanctum')
            ->getJson('/api/venger/dashboard')
            ->assertOk()
            ->assertJsonStructure(['stats' => [
                'clients_count',
                'results_count',
            ]]);

        // Egasi ham platform user — /venger API o‘qishi mumkin
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/venger/dashboard')
            ->assertOk();
    }

    public function test_owner_can_create_venger_staff(): void
    {
        $owner = User::create([
            'name' => 'BOSS',
            'email' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/venger/staff', [
                'name' => 'Ali Venger',
                'login' => 'ali_venger',
                'password' => 'pass1234',
            ])
            ->assertCreated()
            ->assertJsonPath('login', 'ali_venger');

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/venger')
            ->assertOk()
            ->assertJsonPath('login_path', '/kirish')
            ->assertJsonPath('stats.staff_count', 1)
            ->assertJsonStructure([
                'stats' => ['ponds_open', 'ponds_final', 'clients_count'],
                'staff' => [['clients_count', 'open_count', 'final_count']],
                'recent_activity',
                'analytics' => [
                    'clients_by_region',
                    'clients_by_district',
                    'results_by_year',
                    'feed_types' => [['type', 'label', 'kg']],
                    'averages' => [
                        'fcr',
                        'area_ha',
                        'yield_per_ha_kg',
                        'completion_rate',
                    ],
                    'totals' => ['feed_kg', 'extruder_kg', 'granula_kg', 'biomass_gain_kg', 'area_ha', 'regions_count'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'login' => 'ali_venger',
            'type' => User::TYPE_SUPERADMIN_STAFF,
            'company_id' => null,
        ]);

        // Venger hodim: kompaniya ID = 0 + login + parol
        $this->postJson('/api/auth/login', [
            'company_id' => 0,
            'login' => 'ali_venger',
            'password' => 'pass1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'venger')
            ->assertJsonPath('user.login', 'ali_venger');

        $staffId = User::query()->where('login', 'ali_venger')->value('id');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/owner/venger/staff/{$staffId}")
            ->assertOk();

        $this->assertDatabaseMissing('users', [
            'login' => 'ali_venger',
        ]);
    }
}
