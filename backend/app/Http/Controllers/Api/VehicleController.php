<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\UserVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ActivityLogger;

class VehicleController
{
    private function user(Request $request)
    {
        return User::findOrFail($request->session()->get('user_id'));
    }

    public function index(Request $request)
    {
        $user = $this->user($request);

        if ($user->role === 'resident') {
            $residentId = DB::table('residents')->where('user_id', $user->id)->value('id');
            return Vehicle::where('resident_id', $residentId)->orderBy('id')->get();
        }

        return UserVehicle::where('user_id', $user->id)->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $user = $this->user($request);
        $data = $request->validate([
            'plate_number' => ['required', 'string', 'max:32'],
            'vehicle_type' => ['required', 'in:car,motorcycle,truck,other'],
            'color' => ['nullable', 'string', 'max:64'],
        ]);

        $data['plate_number'] = strtoupper($data['plate_number']);
        $data['color'] = ucwords(strtolower($data['color'] ?? ''));

        if ($user->role === 'resident') {
            $residentId = DB::table('residents')->where('user_id', $user->id)->value('id');
            abort_unless($residentId, 422, 'Resident profile not found.');
            abort_if(Vehicle::where('resident_id', $residentId)->count() >= 10, 422, 'Vehicle limit reached.');
            $data['resident_id'] = $residentId;
            $vehicle = Vehicle::create($data);
            ActivityLogger::log($request, $user, 'add_vehicle', 'Added vehicle ' . $vehicle->plate_number);
            return response()->json($vehicle, 201);
        }

        abort_unless(in_array($user->role, ['guard', 'admin']), 403);
        abort_if(UserVehicle::where('user_id', $user->id)->count() >= 5, 422, 'Vehicle limit reached.');
        $data['user_id'] = $user->id;
        $vehicle = UserVehicle::create($data);
        ActivityLogger::log($request, $user, 'add_vehicle', 'Added staff vehicle ' . $vehicle->plate_number);
        return response()->json($vehicle, 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->user($request);

        if ($user->role === 'resident') {
            $residentId = DB::table('residents')->where('user_id', $user->id)->value('id');
            $vehicle = Vehicle::where('resident_id', $residentId)->findOrFail($id);
        } else {
            $vehicle = UserVehicle::where('user_id', $user->id)->findOrFail($id);
        }

        $plate = $vehicle->plate_number;
        $vehicle->delete();
        ActivityLogger::log($request, $user, 'remove_vehicle', 'Removed vehicle ' . $plate);

        return ['ok' => true];
    }
}
