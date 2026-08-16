<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => 'demo.superadmin@goldenhomes.local'],
            [
                'full_name' => 'Demo Super Admin',
                'password' => Hash::make('DemoSuperAdmin123!'),
                'role' => 'admin',
                'status' => 'active',
                'is_super_admin' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'demo.superadmin@goldenhomes.local')
            ->delete();
    }
};
