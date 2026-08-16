<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class RestoreAuthSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('user_id')) {
            $cookie = $request->cookie(config('auth.persistent_cookie', 'smart_gate_auth'));

            if ($cookie) {
                try {
                    $userId = (int) Crypt::decryptString($cookie);

                    if ($userId > 0) {
                        $request->session()->put('user_id', $userId);
                    }
                } catch (DecryptException) {
                    // Ignore stale or invalid persistent cookies.
                }
            }
        }

        return $next($request);
    }
}
