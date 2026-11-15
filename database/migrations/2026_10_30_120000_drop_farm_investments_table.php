<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('farm_investments');
    }

    public function down(): void
    {
        if (Schema::hasTable('farm_investments')) {
            return;
        }

        Schema::create('farm_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('investor', 120);
            $table->decimal('amount', 14, 2);
            $table->date('invested_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
