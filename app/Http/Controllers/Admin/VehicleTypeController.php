<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleTypeRequest;
use App\Http\Requests\UpdateVehicleTypeRequest;
use App\Models\VehicleType;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vehicleTypes = VehicleType::all();
        return view('admin.vehicletypes.index', compact('vehicleTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.vehicletypes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleTypeRequest $request)
    {
        $request->merge([
            'needs_oxygen_check' => $request->boolean('needs_oxygen_check'),
        ]);

        $data = $request->validated();

        $newVehicleType = VehicleType::create([
            'name' => $data['name'],
            'needs_oxygen_check' => $data['needs_oxygen_check'] ?? false,
            'extinguishers_required' => $data['extinguishers_required'],
            'first_inspection_months' => $data['first_inspection_months'],
            'regular_inspection_months' => $data['regular_inspection_months'],
        ]);

        return redirect()
            ->route('admin.vehicletypes.index')
            ->with('status', 'Tipo di veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(VehicleType $vehicleType)
    {
        return view('admin.vehicletypes.show', compact('vehicleType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VehicleType $vehicleType)
    {
        return view('admin.vehicletypes.edit', compact('vehicleType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleTypeRequest $request, VehicleType $vehicleType)
    {
        $request->merge([
            'needs_oxygen_check' => $request->boolean('needs_oxygen_check'),
        ]);

        $data = $request->validated();
        $vehicleType->update([
            'name' => $data['name'],
            'needs_oxygen_check' => $data['needs_oxygen_check'] ?? false,
            'extinguishers_required' => $data['extinguishers_required'],
            'first_inspection_months' => $data['first_inspection_months'],
            'regular_inspection_months' => $data['regular_inspection_months'],
        ]);

        return redirect()
            ->route('admin.vehicletypes.show', $vehicleType->id)
            ->with('status', 'Tipo di veicolo aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VehicleType $vehicleType)
    {
        $vehicleType->delete();

        return redirect()
            ->route('admin.vehicletypes.index')
            ->with('status', 'Tipo di veicolo eliminato con successo.');
    }
}
