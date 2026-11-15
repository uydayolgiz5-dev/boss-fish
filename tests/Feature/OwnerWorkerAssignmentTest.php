<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerWorkerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_must_assign_worker_to_director(): void
    {
        [$owner] = $this->seedOwnerDirectorFarm();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901112244',
                'password' => 'work1234',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['director_id']);
    }

    public function test_owner_creates_worker_with_director_only(): void
    {
        [$owner, $director, $farm] = $this->seedOwnerDirectorFarm();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/workers', [
                'name' => 'Hodim',
                'phone' => '+998901112244',
                'password' => 'work1234',
                'director_id' => $director->id,
            ])
            ->assertCreated()
            ->assertJsonPath('director_id', $director->id);

        $worker = User::where('phone', '+998901112244')->first();
        $this->assertNotNull($worker);
        $this->assertSame(User::TYPE_EMPLOYEE, $worker->type);
        $this->assertSame($director->company_id, $worker->company_id);
        $this->assertNotNull($worker->role_id);
        $this->assertTrue($worker->canAccess('daily_reports'));
        $this->assertTrue($worker->canAccess('ponds'));

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/owner/workers/'.$worker->id, [
                'name' => 'Hodim Yangi',
                'director_id' => $director->id,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Hodim Yangi');
    }

    public function test_worker_login_requires_company_id(): void
    {
        [$director, $farm, , $company] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'login' => 'dirw',
                'phone' => '+998901112255',
            ],
        ]);

        $worker = User::create([
            'name' => 'Hodim',
            'login' => 'work1',
            'phone' => '+998901112266',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $worker->assignedFarms()->sync([$farm->id]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'work1',
            'password' => 'work1234',
        ])->assertOk()
            ->assertJsonPath('user.type', User::TYPE_EMPLOYEE);

        // company_id yo‘q → 422
        $this->postJson('/api/auth/login', [
            'phone' => '+998901112266',
            'password' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_new_director_can_login_immediately(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'boss3@test.local',
            'login' => 'boss3@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
        ]);

        $res = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/directors', [
                'name' => 'Yangi Direktor',
                'phone' => '+998901119999',
                'password' => 'dir9999',
            ])
            ->assertCreated()
            ->json();

        $companyId = (int) ($res['company_id'] ?? User::find($res['id'])?->company_id);
        $login = (string) ($res['login'] ?? '998901119999');

        $this->postJson('/api/auth/login', [
            'company_id' => $companyId,
            'login' => $login,
            'password' => 'dir9999',
        ])->assertOk();
    }

    /** @return array{0: User, 1: User, 2: \App\Models\Farm} */
    private function seedOwnerDirectorFarm(): array
    {
        $owner = $this->makeSuperadmin([
            'email' => 'boss@test.local',
            'login' => 'boss@test.local',
            'password' => 'boss1234',
            'password_plain' => 'boss1234',
        ]);

        [$director, $farm] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Direktor',
                'phone' => '+998901112233',
                'login' => 'dirseeds',
                'password' => 'dir1234',
                'password_plain' => 'dir1234',
                'created_by' => $owner->id,
            ],
        ]);

        return [$owner, $director, $farm];
    }
}
