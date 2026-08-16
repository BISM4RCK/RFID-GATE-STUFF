<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DemoSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
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
}
