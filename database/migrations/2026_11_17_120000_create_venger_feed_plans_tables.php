<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venger_feed_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('venger_feed_plan_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venger_feed_plan_id')->constrained('venger_feed_plans')->cascadeOnDelete();
            $table->foreignId('venger_client_id')->nullable()->constrained('venger_clients')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('client_name');
            $table->string('phone', 32);
            $table->string('location_text')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->decimal('pond_area_ha', 12, 3)->nullable();
            $table->decimal('last_year_feed_kg', 14, 2)->nullable();
            $table->unsignedTinyInteger('rating_10')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedInteger('next_year_carp_count')->nullable();
            $table->decimal('next_year_extruder_kg', 14, 2)->nullable();
            $table->decimal('next_year_granule_kg', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['venger_feed_plan_id', 'sort_order']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venger_feed_plan_rows');
        Schema::dropIfExists('venger_feed_plans');
    }
};
