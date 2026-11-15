<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'farms',
            'ponds',
            'client_subscriptions',
            'pond_create_requests',
            'pond_closure_requests',
            'expenses',
            'farm_investments',
            'finance_sections',
            'warehouse_items',
            'daily_reports',
            'main_reports',
            'fish_operations',
            'activity_logs',
            'tasks',
            'worker_reports',
            'feed_types',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index('company_id');
            });
        }

        // Subscriptions: keep director_id FK; uniqueness moves to company_id when present
        if (Schema::hasTable('client_subscriptions') && Schema::hasColumn('client_subscriptions', 'company_id')) {
            Schema::table('client_subscriptions', function (Blueprint $table) {
                $table->unique('company_id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'farms',
            'ponds',
            'client_subscriptions',
            'pond_create_requests',
            'pond_closure_requests',
            'expenses',
            'farm_investments',
            'finance_sections',
            'warehouse_items',
            'daily_reports',
            'main_reports',
            'fish_operations',
            'activity_logs',
            'tasks',
            'worker_reports',
            'feed_types',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
