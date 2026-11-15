<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_expiration(): void
    {
        config(['auth.spa_remember_days' => 14]);

        Carbon::setTestNow('2026-09-04 12:00:00');

        [$director, , , $company] = $this->createDirectorWithFarmAndPond([
            'director' => [
                'name' => 'Direktor',
                'login' => 'direktor1',
                'phone' => '+998901234567',
                'password' => 'secret',
                'password_plain' => 'secret',
            ],
        ]);

        $response = $this->postJson('/api/auth/login', [
            'company_id' => $company->id,
            'login' => $director->login,
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'director')
            ->assertJsonStructure(['token', 'expires_at']);

        $this->assertSame(
            '2026-09-18T12:00:00+05:00',
            $response->json('expires_at')
        );

        Carbon::setTestNow();
    }

    public function test_owner_login_returns_token_with_expiration(): void
    {
        config(['auth.spa_remember_days' => 30]);

        Carbon::setTestNow('2026-09-04 12:00:00');

        User::create([
            'name' => 'Egasi',
            'login' => 'ega@baliq.uz',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/owner-login', [
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);

        $this->assertSame(
            '2026-10-04T12:00:00+05:00',
            $response->json('expires_at')
        );

        Carbon::setTestNow();
    }
}
