<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'plan')) {
                $table->string('plan', 32)->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('companies', 'billing_period')) {
                $table->string('billing_period', 16)->nullable()->after('plan');
            }
            if (! Schema::hasColumn('companies', 'plan_starts_at')) {
                $table->timestamp('plan_starts_at')->nullable()->after('billing_period');
            }
            if (! Schema::hasColumn('companies', 'plan_price')) {
                $table->unsignedBigInteger('plan_price')->nullable()->after('plan_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['plan', 'billing_period', 'plan_starts_at', 'plan_price'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
