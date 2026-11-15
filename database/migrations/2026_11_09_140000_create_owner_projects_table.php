<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->string('region')->nullable();
            $table->decimal('area_ha', 10, 2)->default(0);
            $table->string('recommended_method')->nullable();
            $table->unsignedTinyInteger('site_score')->nullable();
            $table->string('status')->default('ready');
            $table->json('inputs');
            $table->json('results')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_projects');
    }
};
