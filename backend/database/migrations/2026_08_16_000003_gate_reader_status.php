<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS gate_reader_status (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reader VARCHAR(10) NOT NULL UNIQUE,
                device_id VARCHAR(64) NULL,
                online TINYINT(1) NOT NULL DEFAULT 0,
                last_seen_at DATETIME NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_reader_online (reader, online),
                INDEX idx_last_seen (last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        foreach (['entry', 'exit'] as $reader) {
            DB::table('gate_reader_status')->updateOrInsert(
                ['reader' => $reader],
                ['online' => 0, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gate_reader_status');
    }
};
