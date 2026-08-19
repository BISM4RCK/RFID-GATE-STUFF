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
                'vc.expires_at',
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

        $usageRequestId = $visitor?->visitor_request_id;
        $expiresAt = $visitor?->expires_at;
        if ($expiresAt && now()->greaterThan($expiresAt) && $approved) {
            $approved = false;
            $reason = 'Guest credential has expired.';
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
            'expires_at' => $expiresAt,
        ]);
    }

    public function incidents(Request $request)
    {
        $this->user($request);
        $items = DB::table('gate_logs as gl')
            ->leftJoin('users as u', 'u.id', '=', 'gl.actor_user_id')
            ->select('gl.id','gl.reader','gl.plate_number','gl.event_type','gl.gate_status','gl.log_notes','gl.created_at','u.full_name as account_name')
            ->where(function($q){
                $q->where('gl.log_notes','like','%blacklist%')
                  ->orWhere('gl.event_type','manual_override')
                  ->orWhere('gl.gate_status','denied');
            })
            ->orderByDesc('gl.id')->limit(100)->get();
        $readers = DB::table('gate_reader_status')->orderBy('reader')->get()->map(function($r){
            $r->online = $r->last_seen_at ? now()->diffInSeconds($r->last_seen_at) <= 15 : false;
            return $r;
        });
        return ['ok'=>true,'incidents'=>$items,'reader_incidents'=>$readers->filter(fn($r)=>!$r->online)->values()];
    }

    public function stats(Request $request)
    {
        $admin = $this->admin($request);
        abort_unless((int)$admin->is_super_admin === 1, 403, 'Only the super admin can view gate statistics.');
        $today = now()->startOfDay();
        $week = now()->subDays(6)->startOfDay();
        $approvedToday = DB::table('gate_logs')->where('created_at','>=',$today)->where('gate_status','approved')->count();
        $deniedToday = DB::table('gate_logs')->where('created_at','>=',$today)->where('gate_status','denied')->count();
        $guestToday = DB::table('visitor_requests')->where('created_at','>=',$today)->count();
        $weekly = DB::table('gate_logs')->selectRaw('DATE(created_at) as day, SUM(gate_status = \'approved\') as approved, SUM(gate_status = \'denied\') as denied')->where('created_at','>=',$week)->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $byGate = DB::table('gate_logs')->selectRaw('reader, COUNT(*) as total')->where('created_at','>=',$week)->whereIn('reader',['entry','exit'])->groupBy('reader')->get();
        return ['ok'=>true,'today'=>['approved'=>$approvedToday,'denied'=>$deniedToday,'guests'=>$guestToday],'weekly'=>$weekly,'by_gate'=>$byGate];
    }

    public function exportGateLogs(Request $request)
    {
        $this->admin($request);
        $rows = DB::table('gate_logs as gl')->leftJoin('users as u','u.id','=','gl.actor_user_id')->select('gl.*','u.full_name as account_name','u.email as account_email')->orderByDesc('gl.id')->limit(10000)->get();
        return response()->streamDownload(function() use ($rows){
            $out=fopen('php://output','w'); fputcsv($out,['Time (GMT+8)','Gate','Account','Plate','Event','Result','Notes']);
            foreach($rows as $r) fputcsv($out,[optional($r->created_at)->format('Y-m-d H:i:s') ?? $r->created_at,$r->reader,$r->account_name ?: $r->account_email,$r->plate_number,$r->event_type,$r->gate_status,$r->log_notes]);
            fclose($out);
        }, 'smart-gate-gate-logs.csv', ['Content-Type'=>'text/csv']);
    }

    public function exportAccountLogs(Request $request)
    {
        $this->admin($request);
        $rows=DB::table('account_activity_logs as a')->leftJoin('users as u','u.id','=','a.user_id')->select('a.*','u.full_name','u.email','u.role')->orderByDesc('a.id')->limit(10000)->get();
        return response()->streamDownload(function() use($rows){
            $out=fopen('php://output','w'); fputcsv($out,['Time (GMT+8)','Account','Email','Role','Action','Details','IP']);
            foreach($rows as $r) fputcsv($out,[$r->created_at,$r->full_name ?: $r->account_identifier,$r->email,$r->role ?: $r->account_type,$r->action,$r->details,$r->ip_address]);
            fclose($out);
        }, 'smart-gate-account-logs.csv', ['Content-Type'=>'text/csv']);
    }

    public function alerts(Request $request)
    {
        $this->user($request);
        $alerts = DB::table('gate_logs as gl')
            ->leftJoin('users as u', 'u.id', '=', 'gl.actor_user_id')
            ->select('gl.id','gl.reader','gl.plate_number','gl.event_type','gl.gate_status','gl.log_notes','gl.created_at','u.full_name as account_name')
            ->where('gl.gate_status','denied')
            ->where(function($q){ $q->where('gl.log_notes','like','%blacklist%')->orWhere('gl.log_notes','like','%blacklisted%'); })
            ->orderByDesc('gl.id')->limit(20)->get();
        return ['ok'=>true,'alerts'=>$alerts];
    }

    public function users(Request $request)
    {
        $this->admin($request);
        $users = DB::table('users')->select('id','full_name','username','email','role','status','locked_until','is_super_admin','last_login_at')->orderBy('id')->get()->map(function($x){
            $lastActivity = DB::table('sessions')->where('user_id',$x->id)->max('last_activity');
            $x->online = $lastActivity ? (time() - (int)$lastActivity) <= 300 : false;
            return $x;
        });
        return ['ok' => true, 'users' => $users];
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

    public function deleteAccountLogs(Request $request)
    {
        $admin=$this->admin($request);
        abort_unless((int)$admin->is_super_admin===1,403,'Only the super admin can delete account logs.');
        $count=DB::table('account_activity_logs')->count();
        DB::table('account_activity_logs')->truncate();
        return ['ok'=>true,'deleted'=>$count];
    }

    public function deleteGateLogs(Request $request)
    {
        $admin=$this->admin($request);
        abort_unless((int)$admin->is_super_admin===1,403,'Only the super admin can delete gate logs.');
        $count=DB::table('gate_logs')->count();
        DB::table('gate_logs')->truncate();
        return ['ok'=>true,'deleted'=>$count];
    }

    public function manageVehicle(Request $request)
    {
        $admin=$this->admin($request);
        $data=$request->validate([
            'kind'=>['required','in:resident,staff'],
            'id'=>['required','integer'],
            'action'=>['required','in:update_color,delete'],
            'color'=>['nullable','string','max:64'],
        ]);
        $table=$data['kind']==='resident'?'vehicles':'user_vehicles';
        $vehicle=DB::table($table)->where('id',$data['id'])->first();
        abort_unless($vehicle,404,'Vehicle not found.');
        if($data['action']==='delete'){
            DB::table($table)->where('id',$vehicle->id)->delete();
            ActivityLogger::log($request,$admin,'admin_remove_vehicle','Removed vehicle '.$vehicle->plate_number);
        } else {
            abort_unless(filled($data['color']),422,'Color is required.');
            DB::table($table)->where('id',$vehicle->id)->update(['color'=>$data['color'],'updated_at'=>now()]);
            ActivityLogger::log($request,$admin,'admin_change_vehicle_color','Changed vehicle color for '.$vehicle->plate_number);
        }
        return ['ok'=>true];
    }

    public function createAccount(Request $request)
    {
        $admin = $this->admin($request);

        $data = $request->validate([
            'full_name' => ['required','string','max:150'],
            'email' => ['required','email','max:150','unique:users,email'],
            'username' => ['required','string','max:80','regex:/^[A-Za-z0-9._-]+$/','unique:users,username'],
            'role' => ['required','in:resident,guard,admin'],
            'phase' => ['required_if:role,resident','nullable','regex:/^\d+$/','max:3'],
            'block_number' => ['required_if:role,resident','nullable','string','max:50'],
            'lot_number' => ['required_if:role,resident','nullable','string','max:50'],
            'household_letter' => ['required_if:role,resident','nullable','string','size:1','regex:/^[A-Za-z]$/'],
            'gate_assignment' => ['required_if:role,guard','nullable','in:1,2,3'],
        ]);

        $username = strtoupper(trim($data['username']));
        $password = strtoupper(substr(bin2hex(random_bytes(4)),0,8)) . '!';
        $fullName = ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $data['full_name']))));

        $userId = DB::transaction(function () use ($data, $username, $password, $fullName) {
            $id = DB::table('users')->insertGetId([
                'full_name' => $fullName,
                'username' => $username,
                'email' => strtolower($data['email']),
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role' => $data['role'],
                'status' => 'active',
                'is_super_admin' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($data['role'] === 'resident') {
                $letter = strtoupper($data['household_letter']);
                $house = $data['block_number'].'-'.$data['lot_number'].'-'.$letter;
                DB::table('residents')->insert([
                    'user_id'=>$id,
                    'house_number'=>$house,
                    'block_number'=>$data['block_number'],
                    'lot_number'=>$data['lot_number'],
                    'household_letter'=>$letter,
                    'status'=>'active',
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
                // phase is added by the account-management migration.
                DB::table('residents')->where('user_id',$id)->update(['phase'=>$data['phase']]);
            } elseif ($data['role'] === 'guard') {
                DB::table('guards')->insert([
                    'user_id'=>$id,
                    'guard_code'=>'GRD-'.str_pad((string)$id,3,'0',STR_PAD_LEFT),
                    'gate_assignment'=>$data['gate_assignment'],
                    'status'=>'active',
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            } else {
                DB::table('admins')->insert([
                    'user_id'=>$id,
                    'admin_code'=>'ADM-'.str_pad((string)$id,3,'0',STR_PAD_LEFT),
                    'status'=>'active',
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }

            return $id;
        });

        ActivityLogger::log($request, $admin, 'create_account', "Created {$data['role']} account {$data['email']}");
        return response()->json([
            'ok'=>true,
            'id'=>$userId,
            'username'=>$username,
            'email'=>strtolower($data['email']),
            'temporary_password'=>$password,
            'role'=>$data['role'],
        ], 201);
    }

    public function accountDirectory(Request $request)
    {
        $this->admin($request);
        $residents = DB::table('residents as r')
            ->join('users as u','u.id','=','r.user_id')
            ->select('u.id','u.full_name','u.username','u.email','u.role','u.status','u.locked_until','u.last_login_at','u.is_super_admin',
                     'r.phase','r.block_number','r.lot_number','r.household_letter','r.house_number')
            ->orderBy('r.phase')->orderBy('r.block_number')->orderBy('r.lot_number')->orderBy('r.household_letter')->get();
        $staff = DB::table('users as u')
            ->leftJoin('guards as g','g.user_id','=','u.id')
            ->select('u.id','u.full_name','u.username','u.email','u.role','u.status','u.locked_until','u.last_login_at','u.is_super_admin','g.gate_assignment')
            ->whereIn('u.role',['guard','admin'])->orderBy('u.role')->orderBy('u.full_name')->get();
        return response()->json(['ok'=>true,'residents'=>$residents,'staff'=>$staff]);
    }

    public function addAccountVehicle(Request $request)
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'user_id'=>['required','integer','exists:users,id'],
            'plate_number'=>['nullable','string','max:32','required_unless:vehicle_type,ebike'],
            'vehicle_type'=>['required','in:car,motorcycle,truck,tricycle,ebike,other'],
            'color'=>['nullable','string','max:64'],
        ]);
        $target = DB::table('users')->where('id',$data['user_id'])->first();
        abort_unless($target,404);
        $count = $target->role === 'resident'
            ? DB::table('vehicles')->whereIn('resident_id',DB::table('residents')->where('user_id',$target->id)->pluck('id'))->count()
            : DB::table('user_vehicles')->where('user_id',$target->id)->count();
        abort_if($count >= 20,422,'Vehicle limit reached (20).');
        if ($target->role === 'resident') {
            $profile=DB::table('residents')->where('user_id',$target->id)->first();
            abort_unless($profile,422,'Resident profile not found.');
            $plate=$data['plate_number'] ?? null;
            if ($data['vehicle_type']==='ebike') {
                $plate=$this->generateUniqueEbikePlate();
            }
            abort_unless($data['vehicle_type']==='ebike' || !empty($plate),422,'Plate number is required.');
            $id=DB::table('vehicles')->insertGetId(['resident_id'=>$profile->id,'plate_number'=>strtoupper($plate),'vehicle_type'=>$data['vehicle_type'],'color'=>$data['color']??null,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        } else {
            abort_unless(in_array($target->role,['guard','admin']),422,'Select a valid account.');
            $plate=$data['plate_number'] ?? null;
            if ($data['vehicle_type']==='ebike') {
                $plate=$this->generateUniqueEbikePlate();
            }
            abort_unless(!empty($plate),422,'Plate number is required.');
            $id=DB::table('user_vehicles')->insertGetId(['user_id'=>$target->id,'plate_number'=>strtoupper($plate),'vehicle_type'=>$data['vehicle_type'],'color'=>$data['color']??null,'created_at'=>now()]);
        }
        ActivityLogger::log($request,$admin,'admin_add_vehicle',"Added {$data['vehicle_type']} to {$target->email}: ".($plate??''));
        return response()->json(['ok'=>true,'id'=>$id,'plate_number'=>strtoupper($plate??'')],201);
    }

    private function generateUniqueEbikePlate(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $letters = '';
            for ($i = 0; $i < 4; $i++) {
                $letters .= chr(random_int(65, 90));
            }
            $plate = $letters . ' ' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = DB::table('vehicles')->where('plate_number', $plate)->exists()
                || DB::table('user_vehicles')->where('plate_number', $plate)->exists()
                || DB::table('visitor_request_vehicles')->where('plate_number', $plate)->exists();
            if (!$exists) {
                return $plate;
            }
        }
        abort(500, 'Unable to generate a unique e-bike plate.');
    }

    public function accountAction(Request $request, int $id, string $action)
    {
        $admin=$this->admin($request);
        $target=DB::table('users')->where('id',$id)->first();
        abort_unless($target,404,'Account not found.');
        abort_if((int)$target->is_super_admin===1 && $target->id!==$admin->id,403,'Super admin account cannot be managed.');
        if ($action==='lock') {
            abort_if($target->id === $admin->id, 422, 'You cannot lock your own account.');
            DB::table('users')->where('id',$id)->update(['locked_until'=>now()->addHour(),'failed_login_attempts'=>0,'updated_at'=>now()]);
            DB::table('auth_tokens')->where('user_id',$id)->delete();
            ActivityLogger::log($request,$admin,'lock_account',"Locked account {$target->email} for 1 hour");
            return ['ok'=>true,'locked_until'=>now()->addHour()->toIso8601String()];
        }
        if ($action==='unlock') {
            DB::table('users')->where('id',$id)->update(['locked_until'=>null,'failed_login_attempts'=>0,'updated_at'=>now()]);
            ActivityLogger::log($request,$admin,'unlock_account',"Unlocked account {$target->email}");
            return ['ok'=>true];
        }
        if ($action==='delete') {
            abort_if($target->id===$admin->id,422,'You cannot delete your own account.');
            abort_if((int)$target->is_super_admin===1,403,'The super admin account cannot be deleted.');
            DB::table('users')->where('id',$id)->delete();
            ActivityLogger::log($request,$admin,'delete_account',"Deleted account {$target->email}");
            return ['ok'=>true];
        }
        if ($action==='email') {
            $data=$request->validate(['email'=>['required','email','max:150','unique:users,email,'.$id]]);
            DB::table('users')->where('id',$id)->update(['email'=>$data['email'],'updated_at'=>now()]);
            ActivityLogger::log($request,$admin,'change_email',"Changed email for {$target->email}");
            return ['ok'=>true];
        }
        if ($action==='password') {
            $data=$request->validate(['password'=>['required','string','min:8','max:100']]);
            DB::table('users')->where('id',$id)->update(['password'=>password_hash($data['password'],PASSWORD_BCRYPT),'updated_at'=>now()]);
            ActivityLogger::log($request,$admin,'change_password',"Changed password for {$target->email}");
            return ['ok'=>true];
        }
        abort(404,'Unknown account action.');
    }

    public function accountVehicles(Request $request)
    {
        $this->admin($request);
        $res=DB::table('vehicles as v')->join('residents as r','r.id','=','v.resident_id')->join('users as u','u.id','=','r.user_id')
            ->select('v.*','u.id as user_id','u.full_name as account_name','u.email','u.role','r.phase','r.block_number','r.lot_number','r.household_letter')->get();
        $staff=DB::table('user_vehicles as v')->join('users as u','u.id','=','v.user_id')
            ->select('v.*','u.id as user_id','u.full_name as account_name','u.email','u.role')->get();
        return ['ok'=>true,'resident_vehicles'=>$res,'staff_vehicles'=>$staff];
    }

}
