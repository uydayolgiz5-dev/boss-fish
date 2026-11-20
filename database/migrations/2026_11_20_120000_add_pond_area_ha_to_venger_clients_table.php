<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venger_clients', function (Blueprint $table) {
            $table->decimal('pond_area_ha', 12, 3)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('venger_clients', function (Blueprint $table) {
            $table->dropColumn('pond_area_ha');
        });
    }
};
