<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_requests', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
            $table->index(['resident_id', 'deleted_at'], 'visitor_requests_resident_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_requests', function (Blueprint $table) {
            $table->dropIndex('visitor_requests_resident_deleted_idx');
            $table->dropColumn('deleted_at');
        });
    }
};
