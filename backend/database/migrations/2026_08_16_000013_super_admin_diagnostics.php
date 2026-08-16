<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach ([
            'wifi_rssi' => fn(Blueprint $t) => $t->integer('wifi_rssi')->nullable(),
            'free_heap' => fn(Blueprint $t) => $t->unsignedBigInteger('free_heap')->nullable(),
            'uptime_seconds' => fn(Blueprint $t) => $t->unsignedBigInteger('uptime_seconds')->nullable(),
            'wifi_status' => fn(Blueprint $t) => $t->string('wifi_status',20)->nullable(),
        ] as $column => $definition) {
            if (!Schema::hasColumn('gate_reader_status', $column)) Schema::table('gate_reader_status', $definition);
        }
    }
    public function down(): void {
        foreach (['wifi_rssi','free_heap','uptime_seconds','wifi_status'] as $column) {
            if (Schema::hasColumn('gate_reader_status', $column)) Schema::table('gate_reader_status', fn(Blueprint $t) => $t->dropColumn($column));
        }
    }
};
