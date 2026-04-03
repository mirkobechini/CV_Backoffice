<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleTypeRequest;
use App\Http\Requests\UpdateVehicleTypeRequest;
use App\Models\EquipmentType;
use App\Models\VehicleType;

class VehicleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vehicleTypes = VehicleType::all();
        return view('admin.vehicle-types.index', compact('vehicleTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $equipmentTypes = EquipmentType::all();
        return view('admin.vehicle-types.create', compact('equipmentTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleTypeRequest $request)
    {
        $data = $request->validated();
        $data['needs_oxygen_check'] = $request->boolean('needs_oxygen_check');

        $newVehicleType = VehicleType::create([
            'name' => $data['name'],
            'needs_oxygen_check' => $data['needs_oxygen_check'],
            'first_inspection_months' => $data['first_inspection_months'],
            'regular_inspection_months' => $data['regular_inspection_months'],
        ]);

        $this->syncEquipmentRequirements($newVehicleType, $data);

        return redirect()
            ->route('admin.vehicle-types.index')
            ->with('status', 'Tipo di veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(VehicleType $vehicleType)
    {
        return view('admin.vehicle-types.show', compact('vehicleType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VehicleType $vehicleType)
    {
        $equipmentTypes = EquipmentType::all();
        $equipmentTypeRequirements = $vehicleType->equipmentTypes()->withPivot('required_quantity')->get();

        return view('admin.vehicle-types.edit', compact('vehicleType', 'equipmentTypes', 'equipmentTypeRequirements'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleTypeRequest $request, VehicleType $vehicleType)
    {
        $data = $request->validated();
        $data['needs_oxygen_check'] = $request->boolean('needs_oxygen_check');

        $vehicleType->update([
            'name' => $data['name'],
            'needs_oxygen_check' => $data['needs_oxygen_check'],
            'first_inspection_months' => $data['first_inspection_months'],
            'regular_inspection_months' => $data['regular_inspection_months'],
        ]);

        $this->syncEquipmentRequirements($vehicleType, $data);

        return redirect()
            ->route('admin.vehicle-types.show', $vehicleType->id)
            ->with('status', 'Tipo di veicolo aggiornato con successo.');
    }

    /// Sincronizza le relazioni tra il tipo di veicolo e i tipi di equipaggiamento richiesti
    private function syncEquipmentRequirements(VehicleType $vehicleType, array $data): void
    {
        $equipmentTypeIds = $data['required_equipment_types'] ?? [];
        $requiredQuantities = $data['required_equipment_types_qty'] ?? [];

        if (!is_array($equipmentTypeIds)) {
            $equipmentTypeIds = [$equipmentTypeIds];
        }

        if (!is_array($requiredQuantities)) {
            $requiredQuantities = [$requiredQuantities];
        }

        $syncData = [];

        foreach ($equipmentTypeIds as $index => $equipmentTypeId) {
            if (blank($equipmentTypeId)) {
                continue;
            }

            $syncData[$equipmentTypeId] = [
                'required_quantity' => (int) ($requiredQuantities[$index] ?? 1),
            ];
        }

        $vehicleType->equipmentTypes()->sync($syncData);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VehicleType $vehicleType)
    {
        $vehicleType->delete();

        return redirect()
            ->route('admin.vehicle-types.index')
            ->with('status', 'Tipo di veicolo eliminato con successo.');
    }
}
