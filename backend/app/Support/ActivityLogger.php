<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivityLogger
{
    public static function log(?Request $request, ?object $user, string $action, ?string $details = null): void
    {
        if (!$user) return;
        DB::table('account_activity_logs')->insert([
            'user_id' => $user->id,
            'account_type' => $user->role === 'resident' ? 'resident' : 'staff',
            'account_identifier' => $user->email,
            'action' => $action,
            'details' => $details,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
