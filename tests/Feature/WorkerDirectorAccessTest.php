<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerDirectorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_login_when_company_is_active(): void
    {
        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'boss_ok', 'phone' => '+998901113001'],
        ]);

        $worker = User::create([
            'name' => 'Hodim',
            'login' => 'emp_ok',
            'phone' => '+998901113002',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'emp_ok',
            'password' => 'work1234',
        ])
            ->assertOk()
            ->assertJsonPath('user.company_id', $company->id);
    }

    public function test_employee_cannot_login_when_company_is_inactive(): void
    {
        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => ['is_active' => false],
            'director' => ['login' => 'boss_off', 'phone' => '+998901113003'],
        ]);

        User::create([
            'name' => 'Hodim',
            'login' => 'emp_off',
            'phone' => '+998901113004',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'emp_off',
            'password' => 'work1234',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login'])
            ->assertJsonFragment(['Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.']);
    }

    public function test_employee_login_works_after_company_reactivated(): void
    {
        $sa = $this->makeSuperadmin(['login' => 'sa_reactivate', 'email' => 'sa_reactivate@test.local']);

        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => ['is_active' => false],
            'director' => ['login' => 'boss_re', 'phone' => '+998901113005'],
        ]);

        User::create([
            'name' => 'Hodim',
            'login' => 'emp_re',
            'phone' => '+998901113006',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($sa, 'sanctum')
            ->putJson('/api/superadmin/companies/'.$company->id, ['is_active' => true])
            ->assertOk();

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'emp_re',
            'password' => 'work1234',
        ])->assertOk();
    }
}
