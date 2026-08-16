<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\ActivityLogger;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user && $user->locked_until && now()->lt($user->locked_until)) {
            return response()->json([
                'ok' => false,
                'message' => 'Account is locked until ' . \Carbon\Carbon::parse($user->locked_until)->timezone('Asia/Manila')->format('M d, Y h:i A') . '.',
                'locked_until' => \Carbon\Carbon::parse($user->locked_until)->toIso8601String(),
            ], 423);
        }

        if (! $user || $user->status !== 'active' || ! Hash::check($data['password'], $user->password)) {
            if ($user && $user->status === 'active') {
                $attempts = min(255, ((int) ($user->failed_login_attempts ?? 0)) + 1);
                $updates = ['failed_login_attempts' => $attempts, 'updated_at' => now()];
                if ($attempts >= 5) {
                    $updates['locked_until'] = now()->addHour();
                    $updates['failed_login_attempts'] = 0;
                }
                DB::table('users')->where('id', $user->id)->update($updates);
                if (!empty($updates['locked_until'])) {
                    return response()->json(['ok'=>false,'message'=>'Account locked for 1 hour after too many failed login attempts.'], 423);
                }
            }
            return response()->json(['ok'=>false,'message'=>'Invalid credentials.'], 401);
        }

        if ($user->locked_until && now()->gte($user->locked_until)) {
            $user->forceFill(['locked_until' => null, 'failed_login_attempts' => 0])->save();
        }

        $request->session()->regenerate();
        $request->session()->put('user_id', $user->id);
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        ActivityLogger::log($request, $user, 'login', 'Successful account login');

        $plainToken = Str::random(96);
        DB::table('auth_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'locked_until' => $user->locked_until,
                'is_super_admin' => (bool) $user->is_super_admin,
                'phase' => $user->role === 'resident' ? DB::table('residents')->where('user_id',$user->id)->value('phase') : null,
                'house_number' => $user->role === 'resident' ? DB::table('residents')->where('user_id',$user->id)->value('house_number') : null,
            ],
            'token' => $plainToken,
        ], 200);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = User::find($request->session()->get('user_id'));
        abort_unless($user && $user->status === 'active', 401, 'Unauthenticated.');
        $data = $request->validate([
            'current_password' => ['required','string'],
            'new_password' => ['required','string','min:8','max:100','confirmed'],
        ]);
        abort_unless(Hash::check($data['current_password'], $user->password), 422, 'Current password is incorrect.');
        $user->forceFill(['password' => Hash::make($data['new_password'])])->save();
        DB::table('auth_tokens')->where('user_id',$user->id)->delete();
        $plainToken = Str::random(96);
        DB::table('auth_tokens')->insert([
            'user_id'=>$user->id,'token_hash'=>hash('sha256',$plainToken),'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now(),
        ]);
        ActivityLogger::log($request,$user,'change_own_password','Changed account password');
        return response()->json(['ok'=>true,'message'=>'Password changed successfully.','token'=>$plainToken]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = User::find($request->session()->get('user_id'));
        ActivityLogger::log($request, $user, 'logout', 'Account logout');
        $token = $request->bearerToken();
        if ($token) {
            DB::table('auth_tokens')->where('token_hash', hash('sha256', $token))->delete();
        }
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
                'locked_until' => $user->locked_until,
                'is_super_admin' => (bool) $user->is_super_admin,
                'phase' => $user->role === 'resident' ? DB::table('residents')->where('user_id',$user->id)->value('phase') : null,
                'house_number' => $user->role === 'resident' ? DB::table('residents')->where('user_id',$user->id)->value('house_number') : null,
            ],
        ], 200);
    }
}
