<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expenses')->where('category', 'other')->update(['category' => 'main']);
    }

    public function down(): void
    {
        DB::table('expenses')->where('category', 'main')->update(['category' => 'other']);
    }
};
