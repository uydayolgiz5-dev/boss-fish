<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'next_check_at')) {
                $table->date('next_check_at')->nullable()->after('access_expires_at');
            }
            if (! Schema::hasColumn('companies', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('next_check_at');
            }
            if (! Schema::hasColumn('companies', 'check_interval_days')) {
                $table->unsignedSmallInteger('check_interval_days')->default(0)->after('last_checked_at');
            }
            if (! Schema::hasColumn('companies', 'attention_note')) {
                $table->string('attention_note', 500)->nullable()->after('check_interval_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            foreach (['next_check_at', 'last_checked_at', 'check_interval_days', 'attention_note'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
