<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompaniya obunasi / muddat — login bloklanishi (eski director access_days o‘rniga).
 */
class DirectorAccessDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_created_via_owner_can_login_with_company_id(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'ega@baliq.uz',
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/owner/directors', [
                'name' => 'Direktor',
                'phone' => '+998901112288',
                'password' => 'dir1234',
            ])
            ->assertCreated();

        $companyId = (int) ($response->json('company_id') ?: User::find($response->json('id'))?->company_id);
        $this->assertGreaterThan(0, $companyId);

        $login = (string) ($response->json('login') ?: '998901112288');

        $this->postJson('/api/auth/login', [
            'company_id' => $companyId,
            'login' => $login,
            'password' => 'dir1234',
        ])->assertOk()
            ->assertJsonPath('user.type', User::TYPE_COMPANY_ADMIN)
            ->assertJsonPath('user.company_id', $companyId);
    }

    public function test_inactive_company_blocks_director_login(): void
    {
        [$director, , , $company] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'login' => '998901112299',
                'phone' => '+998901112299',
                'password' => 'dir1234',
                'password_plain' => 'dir1234',
            ],
        ]);

        $company->update(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => $director->login,
            'password' => 'dir1234',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_expired_company_blocks_login_until_reactivated(): void
    {
        [$director, , , $company] = $this->createDirectorWithFarmAndPond([
            'company' => [
                'is_active' => true,
                'access_expires_at' => now()->subDay(),
            ],
            'director' => [
                'login' => '998901112300',
                'phone' => '+998901112300',
                'password' => 'dir1234',
                'password_plain' => 'dir1234',
            ],
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => $director->login,
            'password' => 'dir1234',
        ])->assertStatus(422);

        \App\Models\Company::whereKey($company->id)->update([
            'is_active' => true,
            'access_expires_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => $director->login,
            'password' => 'dir1234',
        ])->assertOk();
    }

    public function test_security_endpoint_lists_company_admins(): void
    {
        $owner = $this->makeSuperadmin([
            'email' => 'ega2@baliq.uz',
            'login' => 'ega2@baliq.uz',
        ]);

        $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Direktor',
                'login' => 'dirsec',
                'phone' => '+998901112301',
            ],
        ]);

        $res = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/owner/security')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($res['directors'] ?? $res);
    }
}
