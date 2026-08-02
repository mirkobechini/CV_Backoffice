<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with(['brand', 'carModel', 'vehicleType'])
            ->when($request->q, fn($q, $search) => $q->search($search))
            ->paginate($request->per_page ?? 20);

        return response()->json($vehicles);
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['brand', 'carModel', 'vehicleType', 'equipment.equipmentType', 'issues', 'deadlines']);

        return response()->json($vehicle);
    }
}
