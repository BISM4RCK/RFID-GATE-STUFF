<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'locked_until')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('locked_until')->nullable()->after('status');
            });
        }
        if (!Schema::hasColumn('users', 'failed_login_attempts')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('locked_until');
            });
        }
        if (!Schema::hasColumn('visitor_requests', 'stay_days')) {
            Schema::table('visitor_requests', function (Blueprint $table) {
                $table->unsignedSmallInteger('stay_days')->default(1)->after('people_count');
            });
        }
        DB::statement("ALTER TABLE visitor_requests MODIFY vehicle_type ENUM('car','motorcycle','truck','tricycle','ebike','other') NOT NULL DEFAULT 'other'");
        DB::statement("ALTER TABLE visitor_request_vehicles MODIFY vehicle_type ENUM('car','motorcycle','truck','tricycle','ebike','other') NOT NULL DEFAULT 'other'");

        if (!Schema::hasColumn('visitor_credentials', 'expires_at')) {
            Schema::table('visitor_credentials', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('barcode_token');
                $table->index('expires_at');
            });
            DB::statement("UPDATE visitor_credentials SET expires_at = DATE_ADD(created_at, INTERVAL 1 DAY) WHERE expires_at IS NULL");
        }

        // Normalize the values that earlier versions stored with display words.
        DB::table('residents')->where('phase', 'like', 'Phase %')->get()->each(function ($row) {
            $phase = preg_replace('/^Phase\s*/i', '', (string) $row->phase);
            DB::table('residents')->where('id', $row->id)->update(['phase' => $phase]);
        });
        DB::table('guards')->whereIn('gate_assignment', ['Entry', 'Exit', 'Entry / Exit'])->get()->each(function ($row) {
            $value = match ($row->gate_assignment) {
                'Entry' => '1',
                'Exit' => '2',
                default => '3',
            };
            DB::table('guards')->where('id', $row->id)->update(['gate_assignment' => $value]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('visitor_request_vehicles')) DB::statement("ALTER TABLE visitor_request_vehicles MODIFY vehicle_type ENUM('car','motorcycle','truck','other') NOT NULL DEFAULT 'other'");
        if (Schema::hasTable('visitor_requests')) DB::statement("ALTER TABLE visitor_requests MODIFY vehicle_type ENUM('car','motorcycle','truck','other') NOT NULL DEFAULT 'other'");
        if (Schema::hasColumn('visitor_credentials', 'expires_at')) {
            Schema::table('visitor_credentials', fn (Blueprint $table) => $table->dropColumn('expires_at'));
        }
        if (Schema::hasColumn('visitor_requests', 'stay_days')) {
            Schema::table('visitor_requests', fn (Blueprint $table) => $table->dropColumn('stay_days'));
        }
        if (Schema::hasColumn('users', 'failed_login_attempts')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('failed_login_attempts'));
        }
        if (Schema::hasColumn('users', 'locked_until')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locked_until'));
        }
    }
};
