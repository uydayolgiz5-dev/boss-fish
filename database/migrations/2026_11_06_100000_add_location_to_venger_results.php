<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venger_results', function (Blueprint $table) {
            $table->string('region', 120)->nullable()->after('title');
            $table->string('district', 120)->nullable()->after('region');
            $table->string('address', 500)->nullable()->after('district');
        });

        // Eski natijalar — mijoz manzilidan to‘ldirish (SQLite + MySQL)
        if (Schema::hasTable('venger_clients')) {
            $rows = DB::table('venger_results')
                ->whereNull('region')
                ->select('id', 'venger_client_id')
                ->get();

            foreach ($rows as $row) {
                $client = DB::table('venger_clients')->where('id', $row->venger_client_id)->first();
                if (! $client) {
                    continue;
                }
                DB::table('venger_results')->where('id', $row->id)->update([
                    'region' => $client->region,
                    'district' => $client->district,
                    'address' => $client->address,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('venger_results', function (Blueprint $table) {
            $table->dropColumn(['region', 'district', 'address']);
        });
    }
};
