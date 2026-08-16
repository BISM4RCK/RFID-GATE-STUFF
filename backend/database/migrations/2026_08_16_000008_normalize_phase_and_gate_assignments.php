<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing demo data used labels. Normalize them to the numeric assignments used by the UI.
        DB::table('guards')->where('gate_assignment','Entry')->update(['gate_assignment'=>'1']);
        DB::table('guards')->where('gate_assignment','Exit')->update(['gate_assignment'=>'2']);
        DB::table('guards')->where('gate_assignment','Entry / Exit')->update(['gate_assignment'=>'3']);

        // Resident phases are stored as the number only (for example, "1", never "Phase 1").
        DB::table('residents')->whereNotNull('phase')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $phase = preg_replace('/[^0-9]/', '', (string) $row->phase);
                DB::table('residents')->where('id', $row->id)->update(['phase' => $phase !== '' ? $phase : null]);
            }
        });
    }

    public function down(): void
    {
        // Keep normalized values; reverting labels would be ambiguous.
    }
};
