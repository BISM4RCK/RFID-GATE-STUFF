<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\ActivityLogger;

class VisitorController extends Controller
{
    private function cleanupExpiredGuests(): void
    {
        DB::table('visitor_requests as vr')
            ->join('visitor_credentials as vc','vc.visitor_request_id','=','vr.id')
            ->whereNotNull('vc.expires_at')
            ->where('vc.expires_at','<=',now())
            ->whereIn('vr.status',['pending','approved'])
            ->update(['vr.status'=>'expired','vr.updated_at'=>now()]);
    }

    public function create(Request $request)
    {
        $this->cleanupExpiredGuests();
        $data = $request->validate([
            'phase' => ['required', 'string', 'max:50'],
            'block_number' => ['required', 'string', 'max:50'],
            'lot_number' => ['required', 'string', 'max:50'],
            'household_letter' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z]$/'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'visitor_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'plate_number' => ['nullable', 'string', 'max:30'],
            'vehicle_type' => ['nullable', 'in:car,motorcycle,truck,tricycle,ebike,other'],
            'vehicles' => ['nullable', 'array', 'max:10'],
            'vehicles.*.plate_number' => ['required', 'string', 'max:30'],
            'vehicles.*.vehicle_type' => ['required', 'in:car,motorcycle,truck,tricycle,ebike,other'],
            'government_id' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
            'purpose_of_visit' => ['required', 'string', 'max:255'],
            'people_count' => ['required', 'integer', 'min:1', 'max:20'],
            'stay_days' => ['required', 'integer', 'min:1', 'max:30'],
            'requested_visit_date' => ['nullable', 'date'],

        ]);

        $vehicles = $data['vehicles'] ?? [];
        if (!$vehicles && !empty($data['plate_number'])) {
            $vehicles = [['plate_number' => $data['plate_number'], 'vehicle_type' => $data['vehicle_type'] ?? 'other']];
        }
        if (count($vehicles) < 1 || count($vehicles) > 10) {
            return response()->json(['ok' => false, 'message' => 'Add between 1 and 10 vehicles.'], 422);
        }
        $plates = array_map(fn($v) => strtoupper(trim($v['plate_number'])), $vehicles);
        if (count($plates) !== count(array_unique($plates))) {
            return response()->json(['ok' => false, 'message' => 'Vehicle plate numbers must be unique.'], 422);
        }

        $resident = DB::table('residents')
            ->where('phase', $data['phase'])
            ->where('block_number', $data['block_number'])
            ->where('lot_number', $data['lot_number'])
            ->where('household_letter', strtoupper($data['household_letter']))
            ->where('status', 'active')
            ->first();

        if (!$resident) {
            return response()->json([
                'ok' => false,
                'message' => 'That resident Phase / Block / Lot / Letter could not be found.',
            ], 422);
        }

        $credential = null;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = strtoupper(Str::random(6));

            if (!DB::table('visitor_credentials')->where('visitor_id', $candidate)->exists()) {
                $credential = $candidate;
                break;
            }
        }

        if (!$credential) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to generate a visitor credential. Please try again.',
            ], 500);
        }

        $requestId = DB::transaction(function () use ($data, $resident, $credential, $vehicles, $request) {
            $id = DB::table('visitor_requests')->insertGetId([
                'resident_id' => $resident->id,
                'house_number' => $resident->house_number,
                'visitor_name' => $data['visitor_name'],
                'contact_number' => $data['contact_number'] ?? null,
                'plate_number' => strtoupper($vehicles[0]['plate_number']),
                'vehicle_type' => $vehicles[0]['vehicle_type'],
                'purpose_of_visit' => $data['purpose_of_visit'],
                'people_count' => $data['people_count'],
                'stay_days' => $data['stay_days'],
                'status' => 'pending',
                'requested_visit_date' => $data['requested_visit_date'] ?? null,
                'qr_reference' => 'GH-' . $credential,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('visitor_notifications')->insert([
                'resident_id'=>$resident->id,
                'visitor_request_id'=>$id,
                'type'=>'visitor_request',
                'title'=>'New visitor request',
                'message'=>$data['visitor_name'].' requested access to your residence.',
                'is_read'=>0,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            $token = Str::random(48);

            DB::table('visitor_credentials')->insert([
                'visitor_request_id' => $id,
                'visitor_id' => $credential,
                'qr_token_hash' => hash('sha256', $token),
                'barcode_token_hash' => hash('sha256', $token),
                'qr_token' => $token,
                'barcode_token' => $token,
                'expires_at' => now()->addDays((int) $data['stay_days']),
                'created_at' => now(),
            ]);

            DB::table('visitor_request_vehicles')->insert([
                'visitor_request_id' => $id,
                'plate_number' => strtoupper($vehicles[0]['plate_number']),
                'vehicle_type' => $vehicles[0]['vehicle_type'],
                'people_count' => $data['people_count'],
                'created_at' => now(),
            ]);
            foreach ($vehicles as $vehicle) {
                if (strtoupper($vehicle['plate_number']) === strtoupper($vehicles[0]['plate_number'])) continue;
                DB::table('visitor_request_vehicles')->insert([
                    'visitor_request_id' => $id,
                    'plate_number' => strtoupper($vehicle['plate_number']),
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'people_count' => $data['people_count'],
                    'created_at' => now(),
                ]);
            }
            if ($request->hasFile('government_id')) {
                $file = $request->file('government_id');
                $path = $file->store('visitor-ids');
                DB::table('visitor_attachments')->insert([
                    'visitor_request_id' => $id,
                    'file_type' => 'government_id',
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $id;
        });

        return response()->json([
            'ok' => true,
            'visitor_id' => $credential,
            'request_id' => $requestId,
            'status' => 'pending',
            'expires_at' => DB::table('visitor_credentials')->where('visitor_id',$credential)->value('expires_at'),
        ], 201);
    }

    public function preRegister(Request $request)
    {
        $this->cleanupExpiredGuests();
        [$user, $residentId] = $this->residentUser($request);

        $data = $request->validate([
            'visitor_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'purpose_of_visit' => ['required', 'string', 'max:255'],
            'people_count' => ['required', 'integer', 'min:1', 'max:20'],
            'stay_days' => ['required', 'integer', 'min:1', 'max:30'],
            'vehicles' => ['required', 'array', 'min:1', 'max:10'],
            'vehicles.*.plate_number' => ['required', 'string', 'max:30'],
            'vehicles.*.vehicle_type' => ['required', 'in:car,motorcycle,truck,tricycle,ebike,other'],
            'government_id' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $resident = DB::table('residents')->where('id', $residentId)->where('status', 'active')->first();
        abort_unless($resident, 403, 'Resident profile not found.');

        $plates = array_map(fn($v) => strtoupper(trim($v['plate_number'])), $data['vehicles']);
        if (count($plates) !== count(array_unique($plates))) {
            return response()->json(['ok' => false, 'message' => 'Vehicle plate numbers must be unique.'], 422);
        }

        $credential = $this->generateCredential();

        $requestId = DB::transaction(function () use ($data, $resident, $user, $residentId, $credential, $request) {
            $id = DB::table('visitor_requests')->insertGetId([
                'resident_id' => $residentId,
                'house_number' => $resident->house_number,
                'visitor_name' => $data['visitor_name'],
                'contact_number' => $data['contact_number'] ?? null,
                'plate_number' => strtoupper($data['vehicles'][0]['plate_number']),
                'vehicle_type' => $data['vehicles'][0]['vehicle_type'],
                'purpose_of_visit' => $data['purpose_of_visit'],
                'people_count' => $data['people_count'],
                'stay_days' => $data['stay_days'],
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'qr_reference' => 'GH-' . $credential,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $token = Str::random(48);
            DB::table('visitor_credentials')->insert([
                'visitor_request_id' => $id,
                'visitor_id' => $credential,
                'qr_token_hash' => hash('sha256', $token),
                'barcode_token_hash' => hash('sha256', $token),
                'qr_token' => $token,
                'barcode_token' => $token,
                'expires_at' => now()->addDays((int) $data['stay_days']),
                'created_at' => now(),
            ]);

            foreach ($data['vehicles'] as $vehicle) {
                DB::table('visitor_request_vehicles')->insert([
                    'visitor_request_id' => $id,
                    'plate_number' => strtoupper($vehicle['plate_number']),
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'people_count' => $data['people_count'],
                    'created_at' => now(),
                ]);
            }

            if ($request->hasFile('government_id')) {
                $file = $request->file('government_id');
                $path = $file->store('visitor-ids');
                DB::table('visitor_attachments')->insert([
                    'visitor_request_id' => $id,
                    'file_type' => 'government_id',
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $id;
        });

        ActivityLogger::log($request, $user, 'pre_register_visitor', "Pre-registered visitor {$data['visitor_name']} with credential {$credential}");

        return response()->json([
            'ok' => true,
            'visitor_id' => $credential,
            'request_id' => $requestId,
            'status' => 'approved',
            'expires_at' => DB::table('visitor_credentials')->where('visitor_id',$credential)->value('expires_at'),
            'vehicles' => $data['vehicles'],
        ], 201);
    }

    private function generateCredential(): string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $candidate = strtoupper(Str::random(6));
            if (!DB::table('visitor_credentials')->where('visitor_id', $candidate)->exists()) {
                return $candidate;
            }
        }
        abort(500, 'Unable to generate visitor credential.');
    }

    public function status($credential)
    {
        $this->cleanupExpiredGuests();
        $row = DB::table('visitor_credentials')
            ->where('visitor_id', strtoupper($credential))
            ->first();

        if (!$row) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
            ], 404);
        }

        $visitor = DB::table('visitor_requests')
            ->where('id', $row->visitor_request_id)
            ->first();

        $status = $visitor->status ?? 'unknown';
        if ($row->expires_at && now()->greaterThan($row->expires_at) && in_array($status, ['pending','approved'], true)) {
            $status = 'expired';
            DB::table('visitor_requests')->where('id',$row->visitor_request_id)->update(['status'=>'expired','updated_at'=>now()]);
        }
        return [
            'ok' => true,
            'visitor_id' => strtoupper($credential),
            'status' => $status,
            'expires_at' => $row->expires_at,
        ];
    }

    public function revoke(Request $request, int $id)
    {
        [$user, $residentId] = $this->residentUser($request);
        $guest = DB::table('visitor_requests')->where('id',$id)->where('resident_id',$residentId)->first();
        abort_unless($guest,404,'Guest record not found.');
        abort_unless(in_array($guest->status,['pending','approved'],true),422,'This guest credential cannot be revoked.');
        DB::table('visitor_requests')->where('id',$id)->update(['status'=>'revoked','updated_at'=>now()]);
        ActivityLogger::log($request,$user,'revoke_guest','Revoked guest credential for '.$guest->visitor_name.'.');
        return ['ok'=>true,'message'=>'Guest credential revoked.'];
    }

    public function guestHistory(Request $request)
    {
        $this->cleanupExpiredGuests();
        [, $residentId] = $this->residentUser($request);
        $requests=DB::table('visitor_requests as vr')->leftJoin('visitor_credentials as vc','vc.visitor_request_id','=','vr.id')
            ->select('vr.id','vr.visitor_name','vr.purpose_of_visit','vr.status','vr.stay_days','vr.created_at','vc.visitor_id','vc.expires_at')
            ->where('vr.resident_id',$residentId)->orderByDesc('vr.id')->limit(200)->get();
        $logs=DB::table('gate_logs')->where('resident_id',$residentId)->whereNotNull('visitor_request_id')->orderByDesc('id')->limit(200)->get();
        return ['ok'=>true,'guests'=>$requests,'gate_events'=>$logs];
    }

    private function residentUser(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && $user->role === 'resident' && $user->status === 'active', 403);
        $residentId = DB::table('residents')->where('user_id', $user->id)->value('id');
        abort_unless($residentId, 403, 'Resident profile not found.');
        return [$user, $residentId];
    }

    public function requests(Request $request)
    {
        $this->cleanupExpiredGuests();
        [, $residentId] = $this->residentUser($request);
        return response()->json([
            'ok' => true,
            'requests' => DB::table('visitor_requests as vr')->leftJoin('visitor_credentials as vc','vc.visitor_request_id','=','vr.id')->select('vr.*','vc.visitor_id')->where('vr.resident_id', $residentId)->orderByDesc('vr.id')->limit(100)->get(),
            'unread_count' => DB::table('visitor_notifications')->where('resident_id',$residentId)->where('is_read',0)->count(),
        ]);
    }

    public function notifications(Request $request)
    {
        [, $residentId] = $this->residentUser($request);
        return response()->json(['ok'=>true,'notifications'=>DB::table('visitor_notifications')->where('resident_id',$residentId)->orderByDesc('id')->limit(50)->get()]);
    }

    public function markNotificationRead(Request $request, int $id)
    {
        [, $residentId] = $this->residentUser($request);
        DB::table('visitor_notifications')->where('id',$id)->where('resident_id',$residentId)->update(['is_read'=>1,'updated_at'=>now()]);
        return ['ok'=>true];
    }

    public function approve(Request $request, int $id)
    {
        [$user, $residentId] = $this->residentUser($request);
        $visitor = DB::table('visitor_requests')->where('id', $id)->where('resident_id', $residentId)->first();
        abort_unless($visitor, 404, 'Visitor request not found.');
        abort_unless($visitor->status === 'pending', 422, 'This visitor request is no longer pending.');

        DB::table('visitor_requests')->where('id', $id)->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        ActivityLogger::log($request, $user, 'approve_visitor', "Approved visitor request #{$id} for {$visitor->visitor_name}");
        return ['ok' => true, 'status' => 'approved'];
    }

    public function reject(Request $request, int $id)
    {
        [$user, $residentId] = $this->residentUser($request);
        $visitor = DB::table('visitor_requests')->where('id', $id)->where('resident_id', $residentId)->first();
        abort_unless($visitor, 404, 'Visitor request not found.');
        abort_unless($visitor->status === 'pending', 422, 'This visitor request is no longer pending.');
        $reason = $request->input('reason', 'Rejected by resident.');
        abort_unless(is_string($reason) && mb_strlen($reason) <= 255, 422, 'Invalid rejection reason.');
        DB::table('visitor_requests')->where('id', $id)->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'updated_at' => now(),
        ]);
        ActivityLogger::log($request, $user, 'reject_visitor', "Rejected visitor request #{$id} for {$visitor->visitor_name}");
        return ['ok' => true, 'status' => 'rejected'];
    }

}
