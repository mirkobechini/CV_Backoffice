<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Vehicle;

class EquipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $equipments = Equipment::with('vehicle', 'equipmentType')->get();
        
        return view('admin.equipments.index', compact('equipments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        $equipmentTypes = EquipmentType::all();
        return view('admin.equipments.create', compact('vehicles', 'equipmentTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentRequest $request)
    {
        $validatedData = $request->validated();

        Equipment::create($validatedData);

        return redirect()->route('admin.equipments.index')->with('status', 'Attrezzatura creata con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Equipment $equipment)
    {
        $equipment->load('vehicle', 'equipmentType');
        return view('admin.equipments.show', compact('equipment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Equipment $equipment)
    {
        $vehicles = Vehicle::all();
        $equipmentTypes = EquipmentType::all();
        return view('admin.equipments.edit', compact('equipment', 'vehicles', 'equipmentTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment)
    {
        $validatedData = $request->validated();

        $equipment->update($validatedData);

        return redirect()->route('admin.equipments.show', $equipment)->with('status', 'Attrezzatura aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Equipment $equipment)
    {
        $equipment->delete();
        return redirect()->route('admin.equipments.index')->with('status', 'Attrezzatura eliminata con successo.');
    }
}
