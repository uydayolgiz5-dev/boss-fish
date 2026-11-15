<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerFarmAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_sees_only_own_company_farm(): void
    {
        [$director, $mine] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Mine'],
            'director' => ['login' => 'mine', 'phone' => '+998901112244'],
            'farm' => ['name' => 'Mening hojaligim'],
        ]);
        $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Other'],
            'director' => ['login' => 'other', 'phone' => '+998901112255'],
            'farm' => ['name' => 'Begona hojalik'],
        ]);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_director_cannot_call_owner_workers_api(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dirx', 'phone' => '+998901112266'],
        ]);

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901110099',
                'password' => '1234',
            ])
            ->assertForbidden();
    }

    public function test_owner_cannot_create_farm_without_director(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'ega2@baliq.uz',
            'login' => 'ega2@baliq.uz',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/farms', [
                'name' => 'Direktorsiz hojalik',
                'region' => 'Toshkent viloyati',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['director_id']);
    }
}
