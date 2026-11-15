<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'access_expires_at')) {
            DB::table('companies')->update(['access_expires_at' => null]);
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'access_expires_at')) {
            DB::table('users')
                ->whereIn('type', ['company_admin', 'employee', 'director', 'worker'])
                ->update(['access_expires_at' => null]);
        }

        if (Schema::hasTable('client_subscriptions')) {
            DB::table('client_subscriptions')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible cleanup of payment/expiry data
    }
};
