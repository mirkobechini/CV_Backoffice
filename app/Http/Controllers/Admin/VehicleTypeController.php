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
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
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
    public function show(VehicleType $vehicleType)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VehicleType $vehicleType)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VehicleType $vehicleType)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VehicleType $vehicleType)
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }
}
