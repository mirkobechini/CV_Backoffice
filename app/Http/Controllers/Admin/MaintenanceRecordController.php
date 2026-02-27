<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $maintenanceRecords = MaintenanceRecord::with(['vehicle', 'provider', 'issue'])->get();
        return view('admin.maintenancerecords.index', compact('maintenanceRecords'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MaintenanceRecord $maintenanceRecord)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaintenanceRecord $maintenanceRecord)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaintenanceRecord $maintenanceRecord)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }
}
