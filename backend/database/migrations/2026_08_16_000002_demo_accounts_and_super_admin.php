<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $residentHash = '$2y$12$U/fVbHa5pOjlBTwFEP9.fOmxj4rdMaQkrIf2cxidLrNDNLBjEpgve';
        $guardHash = '$2y$12$93sScaYynNzoUYKKjXl9k.5fhPqrnWjnv62DtNILLDVelcCGwiNfu';
        $adminHash = '$2y$12$/Q0DaexlZwrfktGy8rYnL.WHXk8i1DV8DwUS5rENAiinXOXzUXgga';
        $superHash = '$2y$12$ecYq3mgF9q69SkoyUqrzpe0ZCPJL5hrl6No.ETei5mjm17YdXTaDO';

        DB::table('users')
            ->whereIn('email', [
                'resident@goldenhomes.local',
                'resident2@goldenhomes.local',
                'resident3@goldenhomes.local',
                'resident4@goldenhomes.local',
                'resident5@goldenhomes.local',
            ])
            ->update(['password' => $residentHash]);

        DB::table('users')
            ->whereIn('email', [
                'guard@goldenhomes.local',
                'guard2@goldenhomes.local',
                'guard3@goldenhomes.local',
            ])
            ->update(['password' => $guardHash]);

        DB::table('users')
            ->whereIn('email', [
                'admin@goldenhomes.local',
                'admin2@goldenhomes.local',
            ])
            ->update(['password' => $adminHash]);

        DB::table('users')->updateOrInsert(
            ['email' => 'kun3h0@goldenhomes.local'],
            [
                'full_name' => 'KUN3H0',
                'password' => $superHash,
                'role' => 'admin',
                'status' => 'active',
                'is_super_admin' => 1,
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'kun3h0@goldenhomes.local')->delete();
    }
};
