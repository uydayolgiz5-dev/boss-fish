<?php

use App\Support\PricingPlans;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'check_interval_days')) {
            return;
        }

        $interval = PricingPlans::DEFAULT_CHECK_INTERVAL;
        $next = now()->addDays($interval)->toDateString();

        DB::table('companies')
            ->where(function ($q) {
                $q->whereNull('check_interval_days')
                    ->orWhere('check_interval_days', '<=', 0);
            })
            ->update([
                'check_interval_days' => $interval,
            ]);

        DB::table('companies')
            ->whereNotNull('plan')
            ->whereNull('next_check_at')
            ->where('is_active', true)
            ->update([
                'next_check_at' => $next,
            ]);
    }

    public function down(): void
    {
        // no-op — backfill
    }
};
