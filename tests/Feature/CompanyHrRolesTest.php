<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CompanyPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyHrRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_created_with_job_roles_and_updated_later(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_hr_'.uniqid(), 'phone' => '+998901110001'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Ali',
                'login' => 'ali_'.uniqid(),
                'password' => '1234',
                'roles' => ['feed_report'],
            ])
            ->assertCreated()
            ->json();

        $this->assertSame(['feed_report'], $created['roles']);
        $this->assertContains('daily_reports', $created['permissions']);
        $this->assertContains('ponds', $created['permissions']);
        $this->assertNotContains('dashboard', $created['permissions']);
        $this->assertNotContains('finance', $created['permissions']);
        $this->assertNotContains('finance_reports', $created['permissions']);
        $this->assertNotContains('activities', $created['permissions']);
        $this->assertNotContains('main_reports', $created['permissions']);

        $updated = $this->actingAs($director, 'sanctum')
            ->putJson('/api/company/employees/'.$created['id'], [
                'roles' => ['warehouse', 'finance_expense'],
            ])
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(['warehouse', 'finance_expense'], $updated['roles']);
        $this->assertContains('warehouse', $updated['permissions']);
        $this->assertContains('finance', $updated['permissions']);
        $this->assertNotContains('finance_reports', $updated['permissions']);
        $this->assertNotContains('daily_reports', $updated['permissions']);

        $employee = User::query()->findOrFail($created['id']);
        $this->assertTrue($employee->canAccess('warehouse'));
        $this->assertTrue($employee->canAccess('finance'));
        $this->assertFalse($employee->canAccess('fish_ops'));
        $this->assertFalse($employee->canAccess('finance_reports'));
    }

    public function test_reports_view_sees_reports_but_not_expense_entry(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_rep_'.uniqid(), 'phone' => '+998901110004'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Nazorat',
                'login' => 'naz_'.uniqid(),
                'password' => '1234',
                'roles' => ['reports_view'],
            ])
            ->assertCreated()
            ->json();

        $this->assertSame(['reports_view'], $created['roles']);
        $this->assertContains('activities', $created['permissions']);
        $this->assertContains('finance_reports', $created['permissions']);
        $this->assertContains('ponds', $created['permissions']);
        $this->assertNotContains('finance', $created['permissions']);
        $this->assertNotContains('daily_reports', $created['permissions']);
        $this->assertNotContains('fish_ops', $created['permissions']);
    }

    public function test_full_manage_grants_all_sections(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_full_'.uniqid(), 'phone' => '+998901110002'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Boss',
                'login' => 'boss_'.uniqid(),
                'password' => '1234',
                'roles' => ['full_manage'],
            ])
            ->assertCreated()
            ->json();

        $this->assertSame(['full_manage'], $created['roles']);
        foreach (CompanyPermissions::ALL as $perm) {
            $this->assertContains($perm, $created['permissions']);
        }
        $this->assertFalse(in_array('employees', $created['permissions'], true));
    }

    public function test_permission_keys_expose_job_roles(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_keys_'.uniqid(), 'phone' => '+998901110003'],
        ]);

        $keys = $this->actingAs($director, 'sanctum')
            ->getJson('/api/company/permission-keys')
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(CompanyPermissions::jobRoleKeys(), $keys['job_roles']);
        $this->assertContains('reports_view', $keys['job_roles']);
        $this->assertNotContains('farm_manage', $keys['job_roles']);
    }

    public function test_feed_employee_me_and_ponds_work_with_saved_roles(): void
    {
        [$director, $farm, $pond] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_feed_'.uniqid(), 'phone' => '+998901110005'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Yemchi',
                'login' => 'yem_'.uniqid(),
                'password' => '1234',
                'roles' => ['feed_report'],
            ])
            ->assertCreated()
            ->json();

        $employee = User::query()->findOrFail($created['id']);

        $list = $this->actingAs($director, 'sanctum')
            ->getJson('/api/company/employees')
            ->assertOk()
            ->json();
        $row = collect($list)->firstWhere('id', $created['id']);
        $this->assertSame(['feed_report'], $row['roles']);
        $this->assertContains('daily_reports', $row['permissions']);

        $me = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json();
        $this->assertContains('daily_reports', $me['permissions']);
        $this->assertContains('ponds', $me['permissions']);
        $this->assertNotContains('finance', $me['permissions']);
        $this->assertNotContains('dashboard', $me['permissions']);

        $farms = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk()
            ->json();
        $this->assertNotEmpty($farms);
        $this->assertSame($farm->id, $farms[0]['id']);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms/'.$farm->id.'/ponds')
            ->assertOk()
            ->assertJsonFragment(['id' => $pond->id]);

        $updated = $this->actingAs($director, 'sanctum')
            ->putJson('/api/company/employees/'.$created['id'], [
                'roles' => ['feed_report', 'water_test'],
            ])
            ->assertOk()
            ->json();
        $this->assertEqualsCanonicalizing(['feed_report', 'water_test'], $updated['roles']);
        $this->assertContains('main_reports', $updated['permissions']);
    }

    public function test_superadmin_acting_as_company_can_create_employee(): void
    {
        $owner = $this->makeSuperadmin();
        [, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Baliq Savdo'],
            'director' => ['login' => 'dir_act_'.uniqid(), 'phone' => '+998901110099'],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $company->id)
            ->postJson('/api/company/employees', [
                'name' => 'Hodim',
                'login' => 'hodim_'.uniqid(),
                'password' => '1234',
                'roles' => ['feed_report', 'warehouse'],
            ])
            ->assertCreated()
            ->assertJsonPath('company_id', $company->id);
    }
}
