<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function store(Request $request)
    {
        $request->merge([
            'needs_oxygen_check' => $request->boolean('needs_oxygen_check'),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:vehicle_types,name',
            'needs_oxygen_check' => 'boolean',
            'extinguishers_required' => 'required|integer|min:0',
            'first_inspection_months' => 'required|integer|min:0',
            'regular_inspection_months' => 'required|integer|min:0',
        ],
        [
            'name.required' => 'Il nome è obbligatorio.',
            'name.string' => 'Il nome deve essere una stringa.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',
            'name.unique' => 'Esiste già un tipo di veicolo con questo nome.',
            'needs_oxygen_check.boolean' => 'Il campo revisione ossigeno deve essere true o false.',
            'extinguishers_required.required' => 'Il numero di estintori è obbligatorio.',
            'extinguishers_required.integer' => 'Il numero di estintori deve essere un intero.',
            'extinguishers_required.min' => 'Il numero di estintori non può essere negativo.',
            'first_inspection_months.required' => 'La durata della prima revisione è obbligatoria.',
            'first_inspection_months.integer' => 'La durata della prima revisione deve essere un intero.',
            'first_inspection_months.min' => 'La durata della prima revisione non può essere negativa.',
            'regular_inspection_months.required' => 'La durata delle revisioni successive è obbligatoria.',
            'regular_inspection_months.integer' => 'La durata delle revisioni successive deve essere un intero.',
            'regular_inspection_months.min' => 'La durata delle revisioni successive non può essere negativa.',
        ]);

        $newVehicleType = new VehicleType();
        $newVehicleType->name = $data['name'];
        $newVehicleType->needs_oxygen_check = $data['needs_oxygen_check'] ?? false;
        $newVehicleType->extinguishers_required = $data['extinguishers_required'];
        $newVehicleType->first_inspection_months = $data['first_inspection_months'];
        $newVehicleType->regular_inspection_months = $data['regular_inspection_months'];
        $newVehicleType->save();

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
    public function update(Request $request, VehicleType $vehicleType)
    {
        $request->merge([
            'needs_oxygen_check' => $request->boolean('needs_oxygen_check'),
        ]);

        $data = $request->validate(
            [
                'name' => 'required|string|max:255|unique:vehicle_types,name,' . $vehicleType->id,
                'needs_oxygen_check' => 'boolean',
                'extinguishers_required' => 'required|integer|min:0',
                'first_inspection_months' => 'required|integer|min:0',
                'regular_inspection_months' => 'required|integer|min:0',
            ],
            [
                'name.required' => 'Il nome è obbligatorio.',
                'name.string' => 'Il nome deve essere una stringa.',
                'name.max' => 'Il nome non può superare i 255 caratteri.',
                'name.unique' => 'Esiste già un tipo di veicolo con questo nome.',
                'needs_oxygen_check.boolean' => 'Il campo revisione ossigeno deve essere true o false.',
                'extinguishers_required.required' => 'Il numero di estintori è obbligatorio.',
                'extinguishers_required.integer' => 'Il numero di estintori deve essere un intero.',
                'extinguishers_required.min' => 'Il numero di estintori non può essere negativo.',
                'first_inspection_months.required' => 'La durata della prima revisione è obbligatoria.',
                'first_inspection_months.integer' => 'La durata della prima revisione deve essere un intero.',
                'first_inspection_months.min' => 'La durata della prima revisione non può essere negativa.',
                'regular_inspection_months.required' => 'La durata delle revisioni successive è obbligatoria.',
                'regular_inspection_months.integer' => 'La durata delle revisioni successive deve essere un intero.',
                'regular_inspection_months.min' => 'La durata delle revisioni successive non può essere negativa.',
            ]
        );
        $vehicleType->name = $data['name'];
        $vehicleType->needs_oxygen_check = $data['needs_oxygen_check'] ?? false;
        $vehicleType->extinguishers_required = $data['extinguishers_required'];
        $vehicleType->first_inspection_months = $data['first_inspection_months'];
        $vehicleType->regular_inspection_months = $data['regular_inspection_months'];
        $vehicleType->update();

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
