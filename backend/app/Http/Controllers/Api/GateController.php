<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ActivityLogger;

class GateController extends Controller
{
    private function device(Request $request): void
    {
        $key = $request->header('X-SmartGate-Device');
        abort_unless(
            $key && hash_equals((string) env('ESP32_DEVICE_KEY', ''), (string) $key),
            401
        );
    }

    private function staff(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && in_array($user->role, ['guard', 'admin']) && $user->status === 'active', 403);
        return $user;
    }

    public function override(Request $request)
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'gate' => ['required', 'in:entry,exit'],
            'emergency' => ['boolean'],
        ]);

        $commandId = DB::table('gate_commands')->insertGetId([
            'issued_by' => $user->id,
            'issued_by_role' => $user->role,
            'command' => 'open',
            'source' => 'guard-dashboard',
            'payload' => json_encode([
                'gate' => $data['gate'],
                'reason' => !empty($data['emergency']) ? 'EMERGENCY' : 'MANUAL_OVERRIDE',
                'requires_acknowledgement' => true,
            ]),
            'status' => 'denied',
            'created_at' => now(),
        ]);

        ActivityLogger::log($request, $user, 'gate_override_requested', strtoupper($data['gate']) . ' gate override requested; acknowledgement required.');

        DB::table('gate_logs')->insert([
            'guard_id' => $user->id,
            'actor_user_id' => $user->id,
            'actor_role' => $user->role,
            'event_type' => 'manual_override',
            'gate_status' => 'manual_override',
            'source_device' => 'guard-dashboard',
            'reader' => $data['gate'],
            'log_notes' => !empty($data['emergency']) ? 'Emergency gate override' : 'Manual gate override',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'command_id' => $commandId,
            'gate' => $data['gate'],
            'requires_acknowledgement' => true,
        ]);
    }

    public function acknowledge(Request $request, int $command)
    {
        $user = $this->staff($request);
        $row = DB::table('gate_commands')->where('id', $command)->where('status', 'denied')->first();
        abort_unless($row, 404, 'Override command not found or already acknowledged.');
        $payload = json_decode($row->payload ?: '{}', true) ?: [];
        abort_unless(!empty($payload['requires_acknowledgement']), 422, 'This command does not require acknowledgement.');

        DB::transaction(function () use ($request, $user, $command, $row, $payload) {
            DB::table('gate_override_acknowledgements')->insert([
                'command_id' => $command,
                'acknowledged_by' => $user->id,
                'acknowledged_at' => now(),
            ]);
            DB::table('gate_commands')->where('id', $command)->update([
                'status' => 'pending',
                'payload' => json_encode(array_merge($payload, ['acknowledged' => true, 'acknowledged_by' => $user->id, 'acknowledged_at' => now()->toIso8601String()])),
            ]);
            ActivityLogger::log($request, $user, 'gate_override_acknowledged', 'Acknowledged gate override #' . $command . ' for ' . strtoupper($payload['gate'] ?? 'gate') . '.');
        });
        return ['ok' => true, 'command_id' => $command, 'gate' => $payload['gate'] ?? null];
    }

    public function commands(Request $request)
    {
        $this->device($request);

        return [
            'ok' => true,
            'commands' => DB::table('gate_commands')
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(10)
                ->get(),
        ];
    }

    public function complete(Request $request, $id)
    {
        $this->device($request);

        $command = DB::table('gate_commands')->where('id', $id)->where('status', 'pending')->first();
        if ($command) {
            DB::table('gate_commands')->where('id', $id)->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $actor = $command->issued_by ? DB::table('users')->where('id', $command->issued_by)->first() : null;
            if ($actor) {
                $payload = json_decode($command->payload ?: '{}', true) ?: [];
                ActivityLogger::log($request, $actor, 'gate_opened', strtoupper($payload['gate'] ?? 'gate') . ' gate command completed by ESP32.');
            }
        }

        return ['ok' => true];
    }

    public function heartbeat(Request $request)
    {
        $this->device($request);

        $deviceId = (string) $request->input('device_id', env('ESP32_DEVICE_ID', '180503'));

        foreach (['entry', 'exit'] as $reader) {
            DB::table('gate_reader_status')->updateOrInsert(
                ['reader' => $reader],
                [
                    'device_id' => $deviceId,
                    'last_seen_at' => now(),
                    'online' => 1,
                    'updated_at' => now(),
                ]
            );
        }

        return ['ok' => true, 'readers' => ['entry' => true, 'exit' => true]];
    }

    public function status()
    {
        $readers = DB::table('gate_reader_status')->orderBy('reader')->get()->map(function ($reader) {
            $reader->online = (bool) $reader->last_seen_at && now()->diffInSeconds($reader->last_seen_at) <= 15;
            return $reader;
        });

        return response()->json([
            'ok' => true,
            'readers' => $readers,
            'gate' => $readers->contains(fn ($r) => (bool) $r->online) ? 'online' : 'offline',
        ]);
    }
}
