<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyActiveToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_company_requires_plan_and_sets_expiry(): void
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
            ->postJson('/api/superadmin/companies', [
                'name' => 'Beta Fish',
                'admin_name' => 'Beta Owner',
                'admin_login' => 'beta_owner',
                'admin_password' => 'pass1234',
                'admin_phone' => '+998901234567',
                'plan' => 'cabinet',
                'billing_period' => 'month',
            ])
            ->assertCreated()
            ->json();

        $this->assertTrue($res['is_active']);
        $this->assertSame('cabinet', $res['plan']);
        $this->assertSame('month', $res['billing_period']);
        $this->assertNotNull($res['access_expires_at']);
        $this->assertDatabaseHas('companies', [
            'id' => $res['id'],
            'is_active' => 1,
            'plan' => 'cabinet',
            'billing_period' => 'month',
        ]);

        $company = Company::find($res['id']);
        $this->assertTrue($company->access_expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_inactive_company_blocks_all_company_logins_with_payment_message(): void
    {
        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => ['name' => 'Gamma', 'is_active' => true],
            'director' => ['login' => 'gamma_admin', 'phone' => '+998907777001'],
        ]);

        $employee = User::create([
            'name' => 'Emp',
            'login' => 'gamma_emp',
            'password' => 'work1234',
            'password_plain' => 'work1234',
            'type' => User::TYPE_EMPLOYEE,
            'company_id' => $company->id,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $company->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'gamma_admin',
            'password' => 'test1234',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login'])
            ->assertJsonFragment(['Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.']);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'gamma_emp',
            'password' => 'work1234',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login'])
            ->assertJsonFragment(['Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.']);
    }

    public function test_quiet_mode_hides_checks_but_keeps_company_login(): void
    {
        $sa = User::create([
            'name' => 'SA quiet',
            'login' => 'sa_quiet',
            'email' => 'sa_quiet@test.uz',
            'password' => 'secret123',
            'password_plain' => 'secret123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ]);

        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => [
                'name' => 'Winter Co',
                'is_active' => true,
                'plan' => 'control',
                'check_interval_days' => 7,
                'next_check_at' => now()->subDay()->toDateString(),
            ],
            'director' => ['login' => 'winter_admin', 'phone' => '+998907777009'],
        ]);

        $this->assertTrue($company->fresh()->needsCheck());

        $quiet = $this->actingAs($sa, 'sanctum')
            ->putJson("/api/superadmin/companies/{$company->id}", ['quiet_mode' => true])
            ->assertOk()
            ->json();

        $this->assertTrue($quiet['is_active']);
        $this->assertTrue($quiet['quiet_mode']);
        $this->assertFalse($quiet['needs_check']);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'winter_admin',
            'password' => 'test1234',
        ])->assertOk();

        $loud = $this->actingAs($sa, 'sanctum')
            ->putJson("/api/superadmin/companies/{$company->id}", ['quiet_mode' => false])
            ->assertOk()
            ->json();

        $this->assertFalse($loud['quiet_mode']);
        $this->assertNotNull($loud['next_check_at']);
        $this->assertFalse($loud['needs_check']);
        $this->assertNotNull($admin);
    }

    public function test_expired_company_auto_deactivates_on_login(): void
    {
        [$admin, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => [
                'name' => 'Delta',
                'is_active' => true,
                'plan' => 'control',
                'billing_period' => 'month',
                'plan_starts_at' => now()->subDays(40),
                'access_expires_at' => now()->subDay(),
            ],
            'director' => ['login' => 'delta_admin', 'phone' => '+998907777002'],
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => 'delta_admin',
            'password' => 'test1234',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['Kompaniya nofaol (mavsum yakunlangan yoki to‘lov). BOSS bilan bog‘laning.']);

        $this->assertFalse($company->fresh()->is_active);
    }

    public function test_superadmin_sees_expiring_companies_on_dashboard(): void
    {
        $sa = $this->makeSuperadmin(['login' => 'sa_exp', 'email' => 'sa_exp@test.uz']);

        Company::create([
            'name' => 'Soon',
            'is_active' => true,
            'plan' => 'cabinet',
            'billing_period' => 'year',
            'plan_starts_at' => now()->subDays(355),
            'access_expires_at' => now()->addDays(5),
            'plan_price' => 1299000,
        ]);

        $this->actingAs($sa, 'sanctum')
            ->getJson('/api/owner/dashboard')
            ->assertOk()
            ->assertJsonPath('expiring_count', 1)
            ->assertJsonPath('expiring_companies.0.name', 'Soon');
    }
}
