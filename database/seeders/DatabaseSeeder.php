<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Superadmin',
            'login' => 'admin',
            'email' => 'admin@bossfish.local',
            'password' => 'admin123',
            'password_plain' => 'admin123',
            'type' => User::TYPE_SUPERADMIN,
            'company_id' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        $this->call([
            CatalogProductSeeder::class,
        ]);
    }
}
