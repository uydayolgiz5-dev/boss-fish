<?php

namespace Tests;

use App\Models\Company;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeSuperadmin(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Superadmin',
            'login' => 'admin',
            'email' => 'admin@test.local',
            'password' => 'admin123',
            'password_plain' => 'admin123',
            'type' => User::TYPE_SUPERADMIN,
            'is_active' => true,
        ], $overrides));
    }

    /** Superadmin kompaniya yaratish uchun majburiy maydonlar */
    protected function companyCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Fish Co',
            'admin_name' => 'Test Admin',
            'admin_login' => 'testadmin',
            'admin_password' => 'pass1234',
            'admin_phone' => '+998901000001',
            'plan' => 'cabinet',
            'billing_period' => 'month',
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: Farm, 2: Pond, 3: Company}
     */
    protected function createDirectorWithFarmAndPond(array $overrides = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Test kompaniya',
            'is_active' => true,
            'access_expires_at' => null,
        ], $overrides['company'] ?? []));

        $director = User::create(array_merge([
            'name' => 'Direktor',
            'login' => 'admin'.$company->id,
            'phone' => '+998901112233',
            'password' => 'test1234',
            'password_plain' => 'test1234',
            'type' => User::TYPE_COMPANY_ADMIN,
            'company_id' => $company->id,
            'is_active' => true,
            'access_expires_at' => null,
        ], $overrides['director'] ?? []));

        $farm = Farm::withoutGlobalScopes()->create(array_merge([
            'name' => 'Test hojalik',
            'region' => 'Sirdaryo',
            'company_id' => $company->id,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ], $overrides['farm'] ?? []));

        $pond = Pond::withoutGlobalScopes()->create(array_merge([
            'farm_id' => $farm->id,
            'company_id' => $company->id,
            'name' => 'Test havza',
            'region' => $farm->region,
            'area_m2' => 1000,
            'current_stock' => 0,
            'director_id' => $director->id,
            'created_by' => $director->id,
            'status' => 'active',
        ], $overrides['pond'] ?? []));

        return [$director, $farm, $pond, $company];
    }

    protected function warehouseIntake(User $director, Farm $farm, array $payload): array
    {
        return $this->actingAs($director, 'sanctum')
            ->postJson("/api/director/farms/{$farm->id}/warehouse", array_merge([
                'unit' => 'kg',
                'unit_price' => 1000,
            ], $payload))
            ->assertCreated()
            ->json();
    }

    /** @deprecated Use createDirectorWithFarmAndPond() */
    protected function createDirectorWithBranchAndPond(array $overrides = []): array
    {
        if (isset($overrides['branch'])) {
            $overrides['farm'] = array_merge($overrides['branch'], $overrides['farm'] ?? []);
            unset($overrides['branch']);
        }

        return $this->createDirectorWithFarmAndPond($overrides);
    }
}
