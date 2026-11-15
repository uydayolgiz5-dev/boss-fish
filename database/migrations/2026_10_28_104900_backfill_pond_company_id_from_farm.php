<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ponds') || ! Schema::hasColumn('ponds', 'company_id')) {
            return;
        }

        DB::statement('
            UPDATE ponds
            SET company_id = (
                SELECT farms.company_id
                FROM farms
                WHERE farms.id = ponds.farm_id
                  AND farms.company_id IS NOT NULL
            )
            WHERE company_id IS NULL
        ');
    }

    public function down(): void
    {
        // no-op
    }
};
