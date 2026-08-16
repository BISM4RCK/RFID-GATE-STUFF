<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RfidController extends Controller
{
    private function device(Request $request): void
    {
        $key = $request->header('X-SmartGate-Device');
        abort_unless($key && hash_equals((string) env('ESP32_DEVICE_KEY', ''), (string) $key), 401);
    }

    public function scan(Request $request)
    {
        $this->device($request);

        $data = $request->validate([
            'rfid_uid' => ['required', 'string', 'max:100'],
            'reader' => ['required', 'in:entry,exit'],
            'device_id' => ['nullable', 'string', 'max:64'],
        ]);

        $uid = strtoupper(trim($data['rfid_uid']));
        $reader = $data['reader'];

        $card = DB::table('rfid_cards as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'c.vehicle_id')
            ->whereRaw('UPPER(c.uid) = ?', [$uid])
            ->where('c.status', 'active')
            ->select('c.*', 'u.id as account_id', 'u.full_name as account_name', 'u.email as account_email', 'u.role as account_role', 'v.id as vehicle_id', 'v.plate_number', 'v.vehicle_type')
            ->first();

        if (!$card) {
            $legacy = DB::table('rfid_tags as t')
                ->join('vehicles as v', 'v.id', '=', 't.vehicle_id')
                ->join('residents as r', 'r.id', '=', 'v.resident_id')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->whereRaw('UPPER(t.uid) = ?', [$uid])
                ->where('t.status', 'active')
                ->select(
                    'u.id as account_id',
                    'u.full_name as account_name',
                    'u.email as account_email',
                    'u.role as account_role',
                    'v.id as vehicle_id',
                    'v.plate_number',
                    'v.vehicle_type',
                    'r.id as resident_id'
                )
                ->first();

            if ($legacy) {
                $card = $legacy;
                $card->status = 'active';
            }
        }

        $plate = $card->plate_number ?? null;
        $residentId = null;
        if ($card && isset($card->account_id)) {
            $residentId = DB::table('residents')->where('user_id', $card->account_id)->value('id');
        }
        if ($card && isset($card->vehicle_id)) {
            $residentId = DB::table('vehicles')->where('id', $card->vehicle_id)->value('resident_id') ?: $residentId;
        }
        $blacklisted = $plate && DB::table('blacklist')
            ->where('status', 'active')
            ->whereRaw('UPPER(plate_number) = ?', [strtoupper($plate)])
            ->exists();

        $approved = $card && !$blacklisted;
        $reason = !$card
            ? 'RFID not registered or inactive.'
            : ($blacklisted ? 'Vehicle is blacklisted.' : 'RFID approved.');

        $logId = DB::table('gate_logs')->insertGetId([
            'resident_id' => $residentId,
            'vehicle_id' => $card->vehicle_id ?? null,
            'guard_id' => null,
            'actor_user_id' => $card->account_id ?? null,
            'actor_role' => $card->account_role ?? null,
            'rfid_uid' => $uid,
            'plate_number' => $plate,
            'event_type' => 'rfid_scan',
            'gate_status' => $approved ? 'approved' : 'denied',
            'source_device' => 'esp32-' . $reader,
            'reader' => $reader,
            'raw_payload' => json_encode($data),
            'log_notes' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'gate_opened' => $approved,
            'gate_status' => $approved ? 'approved' : 'denied',
            'reader' => $reader,
            'gate' => $reader,
            'rfid_uid' => $uid,
            'account' => $card ? [
                'id' => $card->account_id ?? null,
                'name' => $card->account_name ?? null,
                'email' => $card->account_email ?? null,
                'role' => $card->account_role ?? null,
            ] : null,
            'vehicle' => $plate ? [
                'plate_number' => $plate,
                'vehicle_type' => $card->vehicle_type ?? null,
            ] : null,
            'reason' => $reason,
            'log_id' => $logId,
        ]);
    }

    public function events(Request $request)
    {
        $after = (int) $request->query('after_id', 0);

        $query = DB::table('gate_logs as gl')
            ->leftJoin('users as u', 'u.id', '=', 'gl.actor_user_id')
            ->select('gl.*', 'u.full_name as account_name', 'u.email as account_email')
            ->where('gl.event_type', 'rfid_scan')
            ->orderBy('gl.id');

        if ($after > 0) {
            $query->where('gl.id', '>', $after);
        }

        return $query->limit(50)->get();
    }
}
