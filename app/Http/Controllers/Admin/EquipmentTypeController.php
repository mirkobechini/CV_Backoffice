<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentTypeRequest;
use App\Http\Requests\UpdateEquipmentTypeRequest;
use App\Models\EquipmentType;
use Illuminate\Http\Request;

class EquipmentTypeController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $equipmentTypes = EquipmentType::all();
        return view('admin.equipment-types.index', compact('equipmentTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.equipment-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentTypeRequest $request)
    {
        $validatedData = $request->validated();

        EquipmentType::create($validatedData);

        return redirect()->route('admin.equipment-types.index')->with('status', 'Tipo di attrezzatura creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EquipmentType $equipmentType)
    {
        $equipmentType->load('equipments');
        return view('admin.equipment-types.show', compact('equipmentType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EquipmentType $equipmentType)
    {
        return view('admin.equipment-types.edit', compact('equipmentType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentTypeRequest $request, EquipmentType $equipmentType)
    {
        $validatedData = $request->validated();

        $equipmentType->update($validatedData);

        return redirect()->route('admin.equipment-types.show', $equipmentType)->with('status', 'Tipo di attrezzatura aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EquipmentType $equipmentType)
    {
        $equipmentType->delete();
        return redirect()->route('admin.equipment-types.index')->with('status', 'Tipo di attrezzatura eliminato con successo.');
    }
}
