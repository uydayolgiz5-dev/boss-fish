<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'type')) {
                $table->string('type', 32)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('users', 'login')) {
                $table->string('login')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'role_id')) {
                $table->foreignId('role_id')->nullable()->after('type')->constrained('roles')->nullOnDelete();
            }
        });

        // Migrate legacy role → type (fresh installs may be empty)
        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')->where('role', 'owner')->update(['type' => 'superadmin']);
            DB::table('users')->where('role', 'venger')->update(['type' => 'superadmin_staff']);
            DB::table('users')->where('role', 'director')->update(['type' => 'company_admin']);
            DB::table('users')->where('role', 'worker')->update(['type' => 'employee']);
            DB::table('users')->whereNull('type')->update(['type' => 'employee']);
        }

        foreach (DB::table('users')->whereNull('login')->orderBy('id')->get() as $user) {
            $login = $user->email ?: ($user->phone ?: ('user'.$user->id));
            DB::table('users')->where('id', $user->id)->update(['login' => mb_strtolower(trim($login))]);
        }

        DB::table('users')->whereNull('type')->update(['type' => 'employee']);

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'director_id')) {
                $table->dropConstrainedForeignId('director_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['company_id', 'login']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'login']);
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'director_id')) {
                $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('users', 'type')) {
            DB::table('users')->where('type', 'superadmin')->update(['role' => 'owner']);
            DB::table('users')->where('type', 'superadmin_staff')->update(['role' => 'venger']);
            DB::table('users')->where('type', 'company_admin')->update(['role' => 'director']);
            DB::table('users')->where('type', 'employee')->update(['role' => 'worker']);
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropConstrainedForeignId('role_id');
            }
            if (Schema::hasColumn('users', 'login')) {
                $table->dropColumn('login');
            }
            if (Schema::hasColumn('users', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('users', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
