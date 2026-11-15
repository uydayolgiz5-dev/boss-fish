<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_messages', function (Blueprint $table) {
            $table->string('sender_side', 16)->default('boss')->after('sender_id');
        });

        DB::table('company_messages')->whereNull('sender_side')->update(['sender_side' => 'boss']);
    }

    public function down(): void
    {
        Schema::table('company_messages', function (Blueprint $table) {
            $table->dropColumn('sender_side');
        });
    }
};
