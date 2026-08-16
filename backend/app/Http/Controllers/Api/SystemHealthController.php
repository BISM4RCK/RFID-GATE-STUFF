<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function index(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && ($user->role === 'admin' || (int) $user->is_super_admin === 1) && $user->status === 'active', 403);

        $database = true;
        try { DB::select('SELECT 1'); } catch (\Throwable) { $database = false; }

        $mqttHost = env('MQTT_HOST', 'mosquitto');
        $mqttPort = (int) env('MQTT_PORT', 1883);
        $mqtt = false;
        $socket = @fsockopen($mqttHost, $mqttPort, $errno, $errstr, 1.5);
        if ($socket) { $mqtt = true; fclose($socket); }

        $readers = DB::table('gate_reader_status')->orderBy('reader')->get()->map(function ($reader) {
            $reader->online = $reader->last_seen_at ? now()->diffInSeconds($reader->last_seen_at) <= 15 : false;
            return $reader;
        })->values();

        $tunnelConfigured = filled(env('CLOUDFLARE_TUNNEL_ID')) || filled(env('CLOUDFLARE_TUNNEL_TOKEN'));

        return response()->json([
            'ok' => true,
            'checked_at' => now()->toIso8601String(),
            'services' => [
                'database' => ['status' => $database ? 'online' : 'offline'],
                'mqtt' => ['status' => $mqtt ? 'online' : 'offline', 'host' => $mqttHost, 'port' => $mqttPort],
                'esp32_rfid' => ['status' => $readers->contains(fn ($r) => (bool) $r->online) ? 'online' : 'offline', 'readers' => $readers],
                'cloudflare_tunnel' => ['status' => $tunnelConfigured ? 'configured' : 'not_configured'],
            ],
        ]);
    }

    public function devices(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && (int)$user->is_super_admin === 1 && $user->status === 'active', 403);
        $readers=DB::table('gate_reader_status')->orderBy('reader')->get()->map(function($r){
            $r->online=$r->last_seen_at ? now()->diffInSeconds($r->last_seen_at)<=15:false;
            return $r;
        });
        return ['ok'=>true,'devices'=>$readers];
    }

    public function restart(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && (int)$user->is_super_admin === 1 && $user->status === 'active', 403);
        $data=$request->validate(['device_id'=>['required','string','max:64']]);
        $id=DB::table('gate_commands')->insertGetId([
            'issued_by'=>$user->id,'issued_by_role'=>$user->role,'command'=>'restart_device','source'=>'super-admin',
            'payload'=>json_encode(['device_id'=>$data['device_id'],'reason'=>'SUPER_ADMIN_RESTART']), 'status'=>'pending','created_at'=>now(),
        ]);
        return ['ok'=>true,'command_id'=>$id,'message'=>'Restart command queued for the device.'];
    }

}
