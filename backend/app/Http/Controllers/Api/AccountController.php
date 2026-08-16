<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = User::findOrFail($request->session()->get('user_id'));

        $features = [
            'resident' => ['vehicles', 'visitors', 'requests', 'tickets', 'visitor_approval'],
            'guard' => ['gate_override', 'walkin', 'blacklist', 'gate_logs', 'tickets'],
            'admin' => ['gate_override', 'vehicles', 'users', 'blacklist', 'gate_logs', 'admin_guard_logs', 'rfid', 'walkin', 'tickets', 'settings'],
        ];

        return [
            'ok' => true,
            'user' => $user,
            'features' => $user->is_super_admin
                ? array_values(array_unique(array_merge(...array_values($features))))
                : ($features[$user->role] ?? []),
        ];
    }
}
