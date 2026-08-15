<?php
/* BISM4RCK-KUN3H0 2026 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void { DB::unprepared(file_get_contents(database_path('schema/laravel_bootstrap.sql'))); }
    public function down(): void { }
};
