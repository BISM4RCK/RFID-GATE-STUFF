<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Support\ActivityLogger;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('user_id', $user->id);
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLogger::log($request, $user, 'login', 'Successful account login');

        return response()->json([
            'ok' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = User::find($request->session()->get('user_id'));
        ActivityLogger::log($request, $user, 'logout', 'Account logout');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'message' => 'Logged out.',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = User::find($request->session()->get('user_id'));

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
        ], 200);
    }
}
