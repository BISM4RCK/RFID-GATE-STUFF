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
            'plate_number' => ['nullable', 'string', 'max:32','required_unless:vehicle_type,ebike'],
            'vehicle_type' => ['required', 'in:car,motorcycle,truck,tricycle,ebike,other'],
            'color' => ['nullable', 'string', 'max:64'],
        ]);

        $data['plate_number'] = isset($data['plate_number']) ? strtoupper($data['plate_number']) : null;
        
        if ($user->role === 'resident') {
            $residentId = DB::table('residents')->where('user_id', $user->id)->value('id');
            abort_unless($residentId, 422, 'Resident profile not found.');
            abort_if(Vehicle::where('resident_id', $residentId)->count() >= 20, 422, 'Vehicle limit reached.');
            $data['resident_id'] = $residentId;
            $vehicle = Vehicle::create($data);
            ActivityLogger::log($request, $user, 'add_vehicle', 'Added vehicle ' . $vehicle->plate_number);
            return response()->json($vehicle, 201);
        }

        abort_unless(in_array($user->role, ['guard', 'admin']), 403);
        abort_if(UserVehicle::where('user_id', $user->id)->count() >= 20, 422, 'Vehicle limit reached.');
        $data['user_id'] = $user->id;
        $vehicle = UserVehicle::create($data);
        ActivityLogger::log($request, $user, 'add_vehicle', 'Added staff vehicle ' . $vehicle->plate_number);
        return response()->json($vehicle, 201);
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
