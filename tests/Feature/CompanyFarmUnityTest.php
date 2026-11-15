<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Farm;
use App\Models\User;
use App\Support\FarmAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyFarmUnityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_company_also_creates_exactly_one_farm(): void
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
            ->postJson('/api/superadmin/companies', $this->companyCreatePayload([
                'name' => 'Oltin Baliq',
                'admin_name' => 'Ali',
                'admin_login' => 'ali',
                'admin_password' => 'pass1234',
                'admin_phone' => '+998901112200',
            ]))
            ->assertCreated();

        $company = Company::first();
        $this->assertNotNull($company);
        $this->assertSame(1, Farm::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertSame('Oltin Baliq', Farm::withoutGlobalScopes()->where('company_id', $company->id)->first()->name);
    }

    public function test_ensure_for_company_is_idempotent(): void
    {
        $company = Company::create(['name' => 'A', 'is_active' => true]);
        $admin = User::create([
            'name' => 'A',
            'login' => 'a1',
            'password' => 'pass1234',
            'type' => User::TYPE_COMPANY_ADMIN,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $f1 = FarmAccess::ensureForCompany($company, $admin);
        $f2 = FarmAccess::ensureForCompany($company, $admin);

        $this->assertSame($f1->id, $f2->id);
        $this->assertSame(1, Farm::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_company_admin_farms_endpoint_auto_creates_farm(): void
    {
        $company = Company::create(['name' => 'B', 'is_active' => true, 'access_expires_at' => now()->addMonth()]);
        $admin = User::create([
            'name' => 'B',
            'login' => 'b1',
            'password' => 'pass1234',
            'type' => User::TYPE_COMPANY_ADMIN,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $this->assertSame(0, Farm::withoutGlobalScopes()->where('company_id', $company->id)->count());

        $list = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/company/farms')
            ->assertOk()
            ->json();

        $this->assertCount(1, $list);
        $this->assertSame($company->id, $list[0]['company_id']);
    }
}
