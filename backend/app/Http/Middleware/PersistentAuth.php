<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PersistentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUserId = $request->session()->get('user_id');
        if ($sessionUserId) {
            $locked = DB::table('users')->where('id', $sessionUserId)->whereNotNull('locked_until')->where('locked_until', '>', now())->exists();
            if ($locked) {
                $request->session()->forget('user_id');
            }
        }

        if (! $request->session()->has('user_id')) {
            $header = $request->bearerToken();
            if ($header) {
                $hash = hash('sha256', $header);
                $token = DB::table('auth_tokens')->where('token_hash', $hash)->where('expires_at', '>', now())->first();
                if ($token) {
                    $user = DB::table('users')->where('id', $token->user_id)->where('status', 'active')->first();
                    $locked = $user && $user->locked_until && now()->lt($user->locked_until);
                    if ($user && ! $locked) {
                        $request->session()->put('user_id', $user->id);
                        DB::table('auth_tokens')->where('id', $token->id)->update(['last_used_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
        return $next($request);
    }
}
