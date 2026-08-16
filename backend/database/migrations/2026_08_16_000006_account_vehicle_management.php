<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('residents', function (Blueprint $table) {
            $table->string('phase', 50)->nullable()->after('house_number');
        });
        Schema::table('guards', function (Blueprint $table) {
            $table->string('gate_assignment', 50)->nullable()->after('guard_code');
        });
        DB::statement("ALTER TABLE vehicles MODIFY vehicle_type ENUM('car','motorcycle','truck','tricycle','ebike','other') NOT NULL DEFAULT 'other'");
        DB::table('residents')->whereNull('phase')->update(['phase'=>'Phase 1']);
        DB::table('guards')->whereNull('gate_assignment')->update(['gate_assignment'=>'Entry']);
        DB::table('guards')->where('user_id', function($q){ $q->select('id')->from('users')->where('email','guard2@goldenhomes.local')->limit(1); })->update(['gate_assignment'=>'Exit']);
        DB::table('guards')->where('user_id', function($q){ $q->select('id')->from('users')->where('email','guard3@goldenhomes.local')->limit(1); })->update(['gate_assignment'=>'Entry / Exit']);
    }
    public function down(): void {
        DB::statement("ALTER TABLE vehicles MODIFY vehicle_type ENUM('car','motorcycle','truck','other') NOT NULL DEFAULT 'other'");
        Schema::table('guards', fn(Blueprint $table) => $table->dropColumn('gate_assignment'));
        Schema::table('residents', fn(Blueprint $table) => $table->dropColumn('phase'));
    }
};