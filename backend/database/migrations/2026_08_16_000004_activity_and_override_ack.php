<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS account_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            account_type VARCHAR(20) NOT NULL,
            account_identifier VARCHAR(120) NULL,
            action VARCHAR(80) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_type(account_type), KEY idx_user(user_id), KEY idx_action(action), KEY idx_created(created_at),
            CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::statement("CREATE TABLE IF NOT EXISTS gate_override_acknowledgements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            command_id BIGINT UNSIGNED NOT NULL UNIQUE,
            acknowledged_by BIGINT UNSIGNED NOT NULL,
            acknowledged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ack_user(acknowledged_by),
            CONSTRAINT fk_ack_command FOREIGN KEY(command_id) REFERENCES gate_commands(id) ON DELETE CASCADE,
            CONSTRAINT fk_ack_user FOREIGN KEY(acknowledged_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS gate_override_acknowledgements');
    }
};
