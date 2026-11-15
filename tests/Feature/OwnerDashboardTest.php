<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_company_and_pond_counts(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'ega@baliq.uz',
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
        ]);

        $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Co 1'],
            'director' => ['login' => 'd1', 'phone' => '+998901110001'],
            'pond' => ['region' => 'Toshkent', 'area_m2' => 1200, 'current_stock' => 5000],
        ]);
        $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Co 2'],
            'director' => ['login' => 'd2', 'phone' => '+998901110002'],
            'pond' => ['region' => 'Samarqand', 'area_m2' => 800.5, 'current_stock' => 3000],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('directors_count', 2)
            ->assertJsonPath('companies_count', 2)
            ->assertJsonPath('ponds_count', 2)
            ->assertJsonPath('total_area_ha', 2000.5)
            ->assertJsonPath('total_fish_count', 8000);
    }

    public function test_directors_list_is_alphabetical(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'boss@baliq.uz',
            'login' => 'boss@baliq.uz',
        ]);

        $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Zafar Co'],
            'director' => ['name' => 'Zafar', 'login' => 'zafar', 'phone' => '+998901110010'],
        ]);
        $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Ali Co'],
            'director' => ['name' => 'Ali', 'login' => 'ali', 'phone' => '+998901110011'],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/directors')
            ->assertOk()
            ->assertJsonPath('0.name', 'Ali')
            ->assertJsonPath('1.name', 'Zafar');
    }
}
