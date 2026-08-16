<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\ActivityLogger;

class StaffController extends Controller
{
    private function user(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && in_array($user->role, ['guard', 'admin']) && $user->status === 'active', 403);
        return $user;
    }

    private function admin(Request $request)
    {
        $user = $this->user($request);
        abort_unless($user->role === 'admin' || (int) $user->is_super_admin === 1, 403);
        return $user;
    }

    public function overview(Request $request)
    {
        $user = $this->user($request);

        $readers = DB::table('gate_reader_status')->orderBy('reader')->get()->map(function ($reader) {
            $reader->online = $reader->last_seen_at
                ? now()->diffInSeconds($reader->last_seen_at) <= 15
                : false;
            return $reader;
        })->values();

        return response()->json([
            'ok' => true,
            'user' => $user,
            'readers' => $readers,
            'recent_logs' => $this->recentLogs(10),
            'blacklist_count' => DB::table('blacklist')->where('status', 'active')->count(),
            'walk_in_count' => DB::table('walk_in_visitors')->where('status', 'active')->count(),
        ]);
    }

    public function logs(Request $request)
    {
        $this->user($request);
        $limit = min(max((int) $request->query('limit', 50), 1), 200);
        $after = (int) $request->query('after_id', 0);

        return response()->json([
            'ok' => true,
            'logs' => $this->recentLogs($limit, $after),
        ]);
    }

    private function recentLogs(int $limit = 10, int $after = 0)
    {
        $query = DB::table('gate_logs as gl')
            ->leftJoin('users as u', 'u.id', '=', 'gl.actor_user_id')
            ->leftJoin('residents as resident_profile', 'resident_profile.id', '=', 'gl.resident_id')
            ->leftJoin('users as resident_user', 'resident_user.id', '=', 'resident_profile.user_id')
            ->select(
                'gl.*',
                'u.full_name as actor_name',
                'u.email as actor_email',
                'resident_user.full_name as account_name',
                'resident_user.email as account_email'
            )
            ->whereIn('gl.reader', ['entry', 'exit'])
            ->orderByDesc('gl.id')
            ->limit($limit);

        if ($after > 0) {
            $query->where('gl.id', '>', $after)->orderBy('gl.id');
        }

        return $query->get();
    }

    public function blacklist(Request $request)
    {
        $this->user($request);

        return response()->json([
            'ok' => true,
            'items' => DB::table('blacklist as b')
                ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
                ->select('b.*', 'u.full_name as created_by_name')
                ->orderByDesc('b.id')
                ->limit(200)
                ->get(),
        ]);
    }

    public function addBlacklist(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'visitor_name' => ['nullable', 'string', 'max:150'],
            'plate_number' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        abort_unless(!empty($data['visitor_name']) || !empty($data['plate_number']), 422, 'Enter a visitor name or plate number.');

        $id = DB::table('blacklist')->insertGetId([
            'visitor_name' => $data['visitor_name'] ?? null,
            'plate_number' => isset($data['plate_number']) ? strtoupper($data['plate_number']) : null,
            'reason' => $data['reason'],
            'status' => 'active',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLogger::log($request, $user, 'add_blacklist', 'Added blacklist entry for ' . ($data['plate_number'] ?? $data['visitor_name'] ?? 'unknown'));
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function removeBlacklist(Request $request, int $id)
    {
        $user = $this->user($request);
        DB::table('blacklist')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        ActivityLogger::log($request, $user, 'remove_blacklist', "Deactivated blacklist entry #{$id}");
        return ['ok' => true];
    }

    public function walkIns(Request $request)
    {
        $this->user($request);

        return response()->json([
            'ok' => true,
            'items' => DB::table('walk_in_visitors as w')
                ->leftJoin('users as u', 'u.id', '=', 'w.created_by')
                ->select('w.*', 'u.full_name as created_by_name')
                ->orderByDesc('w.id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function addWalkIn(Request $request)
    {
        $user = $this->user($request);

        $data = $request->validate([
            'visitor_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'purpose_of_visit' => ['required', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:30'],
            'vehicle_type' => ['required', 'in:car,motorcycle,truck,other'],
            'people_count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $visitorId = null;
        for ($i = 0; $i < 20; $i++) {
            $candidate = strtoupper(Str::random(6));
            if (!DB::table('walk_in_visitors')->where('visitor_id', $candidate)->exists()) {
                $visitorId = $candidate;
                break;
            }
        }

        abort_unless($visitorId, 500, 'Unable to generate walk-in credential.');

        $token = Str::random(48);

        $id = DB::transaction(function () use ($data, $user, $visitorId, $token) {
            $id = DB::table('walk_in_visitors')->insertGetId([
                'visitor_id' => $visitorId,
                'visitor_name' => $data['visitor_name'],
                'contact_number' => $data['contact_number'] ?? null,
                'purpose_of_visit' => $data['purpose_of_visit'],
                'plate_number' => isset($data['plate_number']) ? strtoupper($data['plate_number']) : null,
                'vehicle_type' => $data['vehicle_type'],
                'barcode_token_hash' => hash('sha256', $token),
                'barcode_token' => $token,
                'created_by' => $user->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($data['plate_number'])) {
                DB::table('walk_in_visitor_vehicles')->insert([
                    'walk_in_id' => $id,
                    'plate_number' => strtoupper($data['plate_number']),
                    'vehicle_type' => $data['vehicle_type'],
                    'people_count' => $data['people_count'],
                    'created_at' => now(),
                ]);
            }

            return $id;
        });

        ActivityLogger::log($request, $user, 'add_walkin', "Created walk-in visitor {$visitorId}");
        return response()->json(['ok' => true, 'id' => $id, 'visitor_id' => $visitorId], 201);
    }

    public function scanVisitor(Request $request)
    {
        $guard = $this->user($request);
        abort_unless($guard->role === 'guard', 403, 'Only guards can scan visitor barcodes.');

        $data = $request->validate([
            'credential' => ['required', 'string', 'max:255'],
            'gate' => ['required', 'in:entry,exit'],
        ]);

        $credential = strtoupper(trim($data['credential']));
        $visitor = DB::table('visitor_credentials as vc')
            ->join('visitor_requests as vr', 'vr.id', '=', 'vc.visitor_request_id')
            ->leftJoin('residents as r', 'r.id', '=', 'vr.resident_id')
            ->leftJoin('users as resident_user', 'resident_user.id', '=', 'r.user_id')
            ->where(function ($q) use ($credential) {
                $q->where('vc.visitor_id', $credential)
                    ->orWhere('vc.barcode_token', $credential);
            })
            ->select(
                'vc.visitor_id',
                'vr.id as visitor_request_id',
                'vr.visitor_name',
                'vr.plate_number',
                'vr.vehicle_type',
                'vr.status as visitor_status',
                'resident_user.id as resident_user_id',
                'resident_user.full_name as resident_name',
                'resident_user.email as resident_email'
            )
            ->first();

        $walkIn = null;
        if (!$visitor) {
            $walkIn = DB::table('walk_in_visitors')
                ->where(function ($q) use ($credential) {
                    $q->where('visitor_id', $credential)
                        ->orWhere('barcode_token', $credential);
                })
                ->first();
        }

        if (!$visitor && !$walkIn) {
            return response()->json([
                'ok' => true,
                'gate_opened' => false,
                'gate_status' => 'denied',
                'reason' => 'Visitor credential not found.',
                'gate' => $data['gate'],
            ]);
        }

        $name = $visitor?->visitor_name ?? $walkIn->visitor_name;
        $plate = $visitor?->plate_number ?? $walkIn->plate_number;
        $status = $visitor?->visitor_status ?? $walkIn->status;
        $account = $visitor?->resident_email ?? null;
        $residentId = $visitor?->resident_user_id
            ? DB::table('residents')->where('user_id', $visitor->resident_user_id)->value('id')
            : null;

        $blacklisted = DB::table('blacklist')
            ->where('status', 'active')
            ->where(function ($q) use ($plate, $name) {
                if ($plate) $q->orWhereRaw('UPPER(plate_number) = ?', [strtoupper($plate)]);
                if ($name) $q->orWhereRaw('LOWER(visitor_name) = ?', [strtolower($name)]);
            })
            ->exists();

        $approved = !$blacklisted && ($status === 'approved' || ($walkIn && $status === 'active'));
        $reason = $blacklisted ? 'Visitor or vehicle is blacklisted.' : ($approved ? 'Visitor approved.' : 'Visitor request is not approved.');

        $vehicleCount = 1;
        $usageRequestId = $visitor?->visitor_request_id;
        if ($usageRequestId) {
            $vehicleCount = max(1, (int) DB::table('visitor_request_vehicles')->where('visitor_request_id', $usageRequestId)->count());
            $usage = DB::table('visitor_gate_usage')->where('visitor_request_id', $usageRequestId)->where('gate', $data['gate'])->first();
            $used = (int) ($usage->scan_count ?? 0);
            if ($used >= $vehicleCount && $approved) {
                $approved = false;
                $reason = 'Visitor barcode limit reached for this gate.';
            }
        } elseif ($walkIn) {
            $vehicleCount = max(1, (int) DB::table('walk_in_visitor_vehicles')->where('walk_in_id', $walkIn->id)->count());
        }

        $logId = DB::table('gate_logs')->insertGetId([
            'resident_id' => $residentId,
            'visitor_request_id' => $visitor?->visitor_request_id,
            'walk_in_id' => $walkIn?->id,
            'guard_id' => $guard->id,
            'actor_user_id' => $guard->id,
            'actor_role' => $guard->role,
            'plate_number' => $plate,
            'event_type' => 'visitor_barcode_scan',
            'gate_status' => $approved ? 'approved' : 'denied',
            'source_device' => 'guard-dashboard',
            'reader' => $data['gate'],
            'log_notes' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commandId = null;
        if ($approved) {
            if ($usageRequestId) {
                $existingUsage = DB::table('visitor_gate_usage')
                    ->where('visitor_request_id', $usageRequestId)
                    ->where('gate', $data['gate'])
                    ->first();
                if ($existingUsage) {
                    DB::table('visitor_gate_usage')->where('id', $existingUsage->id)->update([
                        'scan_count' => ((int) $existingUsage->scan_count) + 1,
                        'last_scanned_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('visitor_gate_usage')->insert([
                        'visitor_request_id' => $usageRequestId,
                        'gate' => $data['gate'],
                        'scan_count' => 1,
                        'last_scanned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $commandId = DB::table('gate_commands')->insertGetId([
                'issued_by' => $guard->id,
                'issued_by_role' => $guard->role,
                'command' => 'open',
                'source' => 'guard-dashboard',
                'payload' => json_encode(['gate' => $data['gate'], 'reason' => 'VISITOR_BARCODE']),
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }

        ActivityLogger::log($request, $guard, 'visitor_barcode_scan', "Scanned visitor {$name} at {$data['gate']} gate: " . ($approved ? 'approved' : 'denied'));

        return response()->json([
            'ok' => true,
            'gate_opened' => $approved,
            'gate_status' => $approved ? 'approved' : 'denied',
            'gate' => $data['gate'],
            'visitor' => [
                'credential' => $visitor?->visitor_id ?? $walkIn->visitor_id,
                'name' => $name,
                'plate_number' => $plate,
                'vehicle_type' => $visitor?->vehicle_type ?? $walkIn->vehicle_type,
                'account' => $account,
            ],
            'reason' => $reason,
            'log_id' => $logId,
            'command_id' => $commandId,
            'vehicle_limit' => $vehicleCount,
        ]);
    }

    public function users(Request $request)
    {
        $this->admin($request);
        return ['ok' => true, 'users' => DB::table('users')->select('id','full_name','email','role','status','is_super_admin','last_login_at')->orderBy('id')->get()];
    }

    public function vehicles(Request $request)
    {
        $this->admin($request);
        return ['ok' => true, 'vehicles' => DB::table('vehicles as v')
            ->join('residents as r', 'r.id', '=', 'v.resident_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->select('v.*','r.house_number','u.full_name as resident_name','u.email as resident_email')
            ->orderByDesc('v.id')->limit(300)->get()];
    }

    public function rfidCards(Request $request)
    {
        $this->admin($request);
        return ['ok' => true, 'cards' => DB::table('rfid_cards as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->select('c.id','c.uid','c.credential_code','c.status','u.full_name','u.email','v.plate_number')
            ->orderByDesc('c.id')->limit(300)->get()];
    }

    public function accountLogs(Request $request)
    {
        $this->admin($request);
        $limit = min(max((int) $request->query('limit', 200), 1), 500);
        $query = DB::table('account_activity_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->select('a.*', 'u.full_name', 'u.email', 'u.role')
            ->orderByDesc('a.id')
            ->limit($limit);
        if ($request->filled('role') && in_array($request->query('role'), ['resident','guard','admin'], true)) {
            $query->where('u.role', $request->query('role'));
        }
        return response()->json(['ok' => true, 'logs' => $query->get()]);
    }

}
