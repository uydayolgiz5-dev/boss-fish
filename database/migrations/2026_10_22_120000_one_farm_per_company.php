<?php

use App\Models\Company;
use App\Models\Farm;
use App\Models\User;
use App\Support\FarmAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: har bir kompaniyaga bitta farm
        if (Schema::hasTable('companies') && Schema::hasTable('farms')) {
            Company::query()->orderBy('id')->each(function (Company $company) {
                FarmAccess::ensureForCompany($company);
            });

            // Ortixcha farmlarni birlashtirish: bir kompaniyada faqat eng eski farm qoladi
            $dupes = DB::table('farms')
                ->select('company_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
                ->whereNotNull('company_id')
                ->groupBy('company_id')
                ->having('c', '>', 1)
                ->get();

            foreach ($dupes as $row) {
                $extras = DB::table('farms')
                    ->where('company_id', $row->company_id)
                    ->where('id', '!=', $row->keep_id)
                    ->pluck('id');

                foreach ($extras as $extraId) {
                    foreach (['ponds', 'warehouse_items', 'expenses', 'farm_investments', 'finance_sections', 'feed_types', 'pond_create_requests'] as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'farm_id')) {
                            DB::table($table)->where('farm_id', $extraId)->update(['farm_id' => $row->keep_id]);
                        }
                    }
                    if (Schema::hasTable('farm_worker')) {
                        DB::table('farm_worker')->where('farm_id', $extraId)->update(['farm_id' => $row->keep_id]);
                    }
                    if (Schema::hasTable('client_subscriptions') && Schema::hasColumn('client_subscriptions', 'farm_id')) {
                        DB::table('client_subscriptions')->where('farm_id', $extraId)->update(['farm_id' => $row->keep_id]);
                    }
                    DB::table('farms')->where('id', $extraId)->delete();
                }
            }
        }

        if (Schema::hasTable('farms') && Schema::hasColumn('farms', 'company_id')) {
            try {
                Schema::table('farms', function (Blueprint $table) {
                    $table->unique('company_id');
                });
            } catch (\Throwable $e) {
                // Index already exists
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('farms') && Schema::hasColumn('farms', 'company_id')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->dropUnique(['company_id']);
            });
        }
    }
};
