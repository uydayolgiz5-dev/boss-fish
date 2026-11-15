<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorOpsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_employee_can_use_daily_reports_but_not_finance_write(): void
    {
        [$director, $farm, $pond] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_perm_'.uniqid(), 'phone' => '+998901110010'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Yemchi',
                'login' => 'yem_perm_'.uniqid(),
                'password' => '1234',
                'roles' => ['feed_report'],
            ])
            ->assertCreated()
            ->json();

        $employee = User::query()->findOrFail($created['id']);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/ponds/'.$pond->id.'/daily-reports')
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms/'.$farm->id.'/finance/overview')
            ->assertForbidden();

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/director/farms/'.$farm->id.'/finance/expenses', [
                'category' => 'other',
                'amount' => 1000,
                'spent_on' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms/'.$farm->id.'/warehouse')
            ->assertForbidden();
    }

    public function test_warehouse_employee_cannot_bypass_via_legacy_worker_api(): void
    {
        [$director, , $pond] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_wh_'.uniqid(), 'phone' => '+998901110019'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Omborchi',
                'login' => 'omb_'.uniqid(),
                'password' => '1234',
                'roles' => ['warehouse'],
            ])
            ->assertCreated()
            ->json();

        $employee = User::query()->findOrFail($created['id']);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/worker/ponds/'.$pond->id.'/daily-reports')
            ->assertForbidden();

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/worker/ponds/'.$pond->id.'/daily-reports', [
                'report_date' => now()->toDateString(),
                'feeding_kg' => 10,
            ])
            ->assertForbidden();

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/worker/ponds/'.$pond->id.'/main-reports')
            ->assertForbidden();
    }

    public function test_owner_cannot_update_profile_while_acting_as_company(): void
    {
        $owner = $this->makeSuperadmin([
            'login' => 'boss_prof_'.uniqid(),
            'email' => 'boss_prof_'.uniqid().'@test.local',
        ]);
        [, , , $company] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_prof_act_'.uniqid(), 'phone' => '+998901110018'],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Acting-Director-Id', (string) $company->id)
            ->putJson('/api/director/profile', [
                'name' => 'Hacker Boss',
                'login' => 'hacked_boss',
            ])
            ->assertForbidden();
    }

    public function test_reports_view_can_read_finance_but_not_write_expense(): void
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_rep2_'.uniqid(), 'phone' => '+998901110011'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Nazoratchi',
                'login' => 'naz2_'.uniqid(),
                'password' => '1234',
                'roles' => ['reports_view'],
            ])
            ->assertCreated()
            ->json();

        $employee = User::query()->findOrFail($created['id']);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/farms/'.$farm->id.'/finance/overview')
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/director/farms/'.$farm->id.'/finance/expenses', [
                'category' => 'other',
                'amount' => 1000,
                'spent_on' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_director_profile_and_audit_and_password_reset(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_prof_'.uniqid(), 'phone' => '+998901110012'],
        ]);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/profile')
            ->assertOk()
            ->assertJsonPath('login', $director->login);

        $this->actingAs($director, 'sanctum')
            ->putJson('/api/director/profile', [
                'name' => 'Yangi Direktor',
                'login' => $director->login,
                'phone' => '+998901110099',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Yangi Direktor');

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/audit-journal')
            ->assertOk();

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/closure-notifications')
            ->assertOk()
            ->assertJsonStructure(['alerts']);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Resetchi',
                'login' => 'reset_'.uniqid(),
                'password' => '1234',
                'roles' => ['warehouse'],
            ])
            ->assertCreated()
            ->json();

        $reset = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees/'.$created['id'].'/reset-password')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($reset['password_plain']);
        $this->assertArrayNotHasKey('password_plain', collect(
            $this->actingAs($director, 'sanctum')->getJson('/api/company/employees')->json()
        )->firstWhere('id', $created['id']) ?: []);

        $employee = User::query()->findOrFail($created['id']);
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/profile')
            ->assertForbidden();
        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/director/audit-journal')
            ->assertForbidden();
    }

    public function test_inactive_role_blocks_employee_section_access(): void
    {
        [$director, $farm] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_inact_'.uniqid(), 'phone' => '+998901110013'],
        ]);

        $created = $this->actingAs($director, 'sanctum')
            ->postJson('/api/company/employees', [
                'name' => 'Faolsiz',
                'login' => 'inact_'.uniqid(),
                'password' => '1234',
                'roles' => ['warehouse'],
            ])
            ->assertCreated()
            ->json();

        $employee = User::query()->findOrFail($created['id']);
        $role = $employee->companyRole;
        $this->assertNotNull($role);
        $role->is_active = false;
        $role->save();
        $employee->unsetRelation('companyRole');

        $this->actingAs($employee->fresh(), 'sanctum')
            ->getJson('/api/director/farms/'.$farm->id.'/warehouse')
            ->assertForbidden();
    }

    public function test_expired_company_is_blocked_on_director_request(): void
    {
        [$director] = $this->createDirectorWithFarmAndPond([
            'director' => ['login' => 'dir_exp_'.uniqid(), 'phone' => '+998901110014'],
        ]);

        $company = $director->company;
        $company->access_expires_at = now()->subDay();
        $company->is_active = true;
        $company->save();

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/director/farms')
            ->assertForbidden();

        $this->assertFalse($company->fresh()->is_active);
    }
}
