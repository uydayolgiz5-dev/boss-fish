<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_creates_company_with_sequential_id_and_admin_login(): void
    {
        $sa = User::create([
            'name' => 'SA',
            'login' => 'sa@test.uz',
            'email' => 'sa@test.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        $res = $this->actingAs($sa, 'sanctum')
            ->postJson('/api/superadmin/companies', $this->companyCreatePayload([
                'name' => 'Alpha Fish',
                'admin_name' => 'Alpha Admin',
                'admin_login' => 'alpha',
                'admin_password' => 'pass1234',
                'admin_phone' => '+998901111000',
            ]))
            ->assertCreated()
            ->json();

        $this->assertSame(1, $res['id']);
        $this->assertSame('alpha', $res['admin']['login']);

        $this->postJson('/api/auth/login', [
            'company_id' => 1,
            'login' => 'alpha',
            'password' => 'pass1234',
        ])->assertOk()
            ->assertJsonPath('user.type', 'company_admin')
            ->assertJsonPath('user.company_id', 1);
    }

    public function test_company_data_is_isolated_by_company_id(): void
    {
        [$adminA, $farmA, $pondA, $companyA] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'A'],
            'director' => ['login' => 'admin-a', 'phone' => '+998901111111'],
        ]);
        [$adminB, $farmB, $pondB, $companyB] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'B'],
            'director' => ['login' => 'admin-b', 'phone' => '+998902222222'],
        ]);

        $this->assertNotSame($companyA->id, $companyB->id);
        $this->assertSame($companyA->id, $farmA->company_id);
        $this->assertSame($companyB->id, $farmB->company_id);

        $listA = $this->actingAs($adminA, 'sanctum')
            ->getJson('/api/company/farms')
            ->assertOk()
            ->json();

        $idsA = collect($listA)->pluck('id')->all();
        $this->assertContains($farmA->id, $idsA);
        $this->assertNotContains($farmB->id, $idsA);

        // Global CompanyScope hides other-tenant rows (404), not 403.
        $this->actingAs($adminA, 'sanctum')
            ->getJson('/api/company/ponds/'.$pondB->id)
            ->assertNotFound();
    }

    public function test_employee_permissions_gate_roles_api(): void
    {
        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'boss1', 'phone' => '+998903333333'],
        ]);

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Only daily',
            'permissions' => ['daily_reports'],
            'is_active' => true,
        ]);

        $employee = User::create([
            'name' => 'Emp',
            'login' => 'emp1',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/company/roles')
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/company/roles')
            ->assertForbidden();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/company/employees')
            ->assertForbidden();

        $me = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json();

        $this->assertSame(['daily_reports'], $me['permissions']);
        $this->assertFalse(in_array('finance', $me['permissions'], true));
    }

    public function test_platform_login_with_company_id_zero(): void
    {
        User::create([
            'name' => 'SA',
            'login' => 'admin',
            'email' => 'admin@bossfish.local',
            'password' => 'admin123',
            'password_plain' => 'admin123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => 0,
            'login' => 'admin',
            'password' => 'admin123',
        ])->assertOk()
            ->assertJsonPath('user.type', 'superadmin');
    }

    public function test_superadmin_can_update_settings_login_and_password(): void
    {
        $sa = User::create([
            'name' => 'SA',
            'login' => 'admin',
            'password' => 'admin123',
            'password_plain' => 'admin123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($sa, 'sanctum')
            ->putJson('/api/superadmin/settings', [
                'name' => 'Boss',
                'login' => 'boss',
                'password' => 'newpass99',
            ])
            ->assertOk()
            ->assertJsonPath('login', 'boss');

        $this->postJson('/api/auth/login', [
            'company_id' => 0,
            'login' => 'boss',
            'password' => 'newpass99',
        ])->assertOk();
    }
}
