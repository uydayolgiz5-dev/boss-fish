<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venger_clients', function (Blueprint $table) {
            $table->string('phone2', 32)->nullable()->after('phone');
            $table->index('phone2');
        });

        Schema::table('venger_feed_plan_rows', function (Blueprint $table) {
            $table->string('phone2', 32)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('venger_feed_plan_rows', function (Blueprint $table) {
            $table->dropColumn('phone2');
        });

        Schema::table('venger_clients', function (Blueprint $table) {
            $table->dropIndex(['phone2']);
            $table->dropColumn('phone2');
        });
    }
};
