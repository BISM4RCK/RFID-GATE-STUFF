<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('visitor_gate_usage')) {
            Schema::create('visitor_gate_usage', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('visitor_request_id');
                $table->enum('gate', ['entry', 'exit']);
                $table->unsignedInteger('scan_count')->default(0);
                $table->timestamp('last_scanned_at')->nullable();
                $table->timestamps();
                $table->unique(['visitor_request_id', 'gate']);
                $table->foreign('visitor_request_id')->references('id')->on('visitor_requests')->cascadeOnDelete();
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('visitor_gate_usage');
    }
};
