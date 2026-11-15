<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_logs_in_with_only_login_and_password(): void
    {
        User::create([
            'name' => 'Egasi',
            'login' => 'ega@baliq.uz',
            'email' => 'ega@baliq.uz',
            'password' => 'owner-pass',
            'password_plain' => 'owner-pass',
            'type' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/owner-login', [
            'login' => 'ega@baliq.uz',
            'password' => 'owner-pass',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonStructure(['token']);
    }
}
