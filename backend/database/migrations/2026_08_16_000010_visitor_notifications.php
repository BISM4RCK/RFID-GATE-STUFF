<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('visitor_notifications')) {
            Schema::create('visitor_notifications', function(Blueprint $table){
                $table->id();
                $table->unsignedBigInteger('resident_id');
                $table->unsignedBigInteger('visitor_request_id')->nullable();
                $table->string('type',50);
                $table->string('title',150);
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index(['resident_id','is_read']);
                $table->foreign('resident_id')->references('id')->on('residents')->cascadeOnDelete();
                $table->foreign('visitor_request_id')->references('id')->on('visitor_requests')->nullOnDelete();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('visitor_notifications'); }
};
