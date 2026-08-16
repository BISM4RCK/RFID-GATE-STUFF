<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 80)->nullable()->unique()->after('full_name');
            });
            DB::table('users')->whereNull('username')->orderBy('id')->get()->each(function ($u) {
                $base = strtolower(preg_replace('/[^a-z0-9]+/', '', explode('@', $u->email)[0])) ?: 'user'.$u->id;
                $candidate = $base;
                $n = 2;
                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $candidate = $base.$n++;
                }
                DB::table('users')->where('id', $u->id)->update(['username' => $candidate]);
            });
        }

        if (!Schema::hasTable('user_vehicles')) {
            Schema::create('user_vehicles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('plate_number', 32);
                $table->string('vehicle_type', 30)->default('other');
                $table->string('color', 64)->nullable();
                $table->timestamps();
                $table->index('user_id');
                $table->index('plate_number');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Keep the original demo resident location deterministic.
        $resident = DB::table('users')->where('email','resident@goldenhomes.local')->first();
        if ($resident) {
            DB::table('residents')->where('user_id',$resident->id)->update([
                'phase'=>'Phase 1',
                'house_number'=>'12-4-A',
                'block_number'=>'12',
                'lot_number'=>'4',
                'household_letter'=>'A',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_vehicles')) Schema::drop('user_vehicles');
        if (Schema::hasColumn('users','username')) Schema::table('users', fn(Blueprint $table) => $table->dropUnique(['username']));
        if (Schema::hasColumn('users','username')) Schema::table('users', fn(Blueprint $table) => $table->dropColumn('username'));
    }
};
