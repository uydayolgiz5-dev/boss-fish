<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerActingDirectorScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $directorA;

    private User $directorB;

    private $farmA;

    private $farmB;

    private $pondA;

    private $pondB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->makeSuperadmin([
            'email' => 'boss@test.local',
            'login' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
        ]);

        [$this->directorA, $this->farmA, $this->pondA] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Co A'],
            'director' => [
                'name' => 'Direktor A',
                'phone' => '+998901112233',
                'login' => 'dira',
            ],
            'farm' => ['name' => 'Hojalik A', 'region' => 'Sirdaryo'],
            'pond' => ['name' => 'Havza A', 'region' => 'Sirdaryo', 'area_m2' => 5],
        ]);

        [$this->directorB, $this->farmB, $this->pondB] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Co B'],
            'director' => [
                'name' => 'Direktor B',
                'phone' => '+998901112244',
                'login' => 'dirb',
            ],
            'farm' => ['name' => 'Hojalik B', 'region' => 'Toshkent'],
            'pond' => ['name' => 'Havza B', 'region' => 'Toshkent', 'area_m2' => 3],
        ]);
    }

    public function test_owner_cannot_access_pond_without_acting_director_header(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertForbidden();
    }

    public function test_owner_can_access_pond_when_acting_as_correct_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->company_id)
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertOk();
    }

    public function test_owner_cannot_access_another_directors_pond_when_acting_as_different_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->company_id)
            ->getJson("/api/director/ponds/{$this->pondB->id}/summary")
            ->assertForbidden();
    }

    public function test_owner_farms_list_requires_acting_director(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk()
            ->assertJsonCount(0);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->company_id)
            ->getJson('/api/director/farms')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->farmA->id, $ids);
        $this->assertNotContains($this->farmB->id, $ids);
    }

    public function test_platform_admin_can_access_pond_when_acting(): void
    {
        $admin = User::create([
            'name' => 'Platform Admin',
            'login' => 'padmin_'.uniqid(),
            'email' => 'padmin_'.uniqid().'@test.local',
            'password' => 'admin1234',
            'password_plain' => 'admin1234',
            'type' => User::TYPE_PLATFORM_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $this->directorA->company_id)
            ->getJson("/api/director/ponds/{$this->pondA->id}/summary")
            ->assertOk();
    }
}
