<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMileageLogRequest;
use App\Http\Requests\UpdateMileageLogRequest;
use App\Models\MileageLog;
use App\Models\Vehicle;

class MileageLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $mileageLogs = MileageLog::query()
            ->with('vehicle')
            ->orderByDesc('log_date')
            ->get();

        return view('admin.mileage-logs.index', compact('mileageLogs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        return view('admin.mileage-logs.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMileageLogRequest $request)
    {
        MileageLog::create($request->validated());
        return redirect()->route('admin.mileage-logs.index')->with('status', 'Chilometraggio creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MileageLog $mileageLog)
    {
        $mileageLog->load('vehicle');
        return view('admin.mileage-logs.show', compact('mileageLog'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MileageLog $mileageLog)
    {
        $vehicles = Vehicle::all();
        return view('admin.mileage-logs.edit', compact('mileageLog', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMileageLogRequest $request, MileageLog $mileageLog)
    {
        $mileageLog->update($request->validated());
        return redirect()->route('admin.mileage-logs.index')->with('status', 'Chilometraggio aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MileageLog $mileageLog)
    {
        $mileageLog->delete();
        return redirect()->route('admin.mileage-logs.index')->with('status', 'Chilometraggio eliminato con successo.');
    }
}
