<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Deadline;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $totalVehicles = Vehicle::count();
        // Guasti aperti
        $openIssues = Issue::with('vehicle')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('event_date')
            ->get();

        // Scadenze imminenti (prossimi 30 giorni)
        $upcomingDeadlines = Deadline::with('vehicle')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(30))
            ->orderBy('due_date')
            ->get();

        // Appuntamenti futuri
        $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider'])
            ->whereNull('return_date')
            ->where('appointment_date', '>=', now())
            ->orderBy('appointment_date')
            ->take(5)
            ->get();

        // Veicoli con equipaggiamento incompleto
        $incompleteVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
            ->get()
            ->filter(fn($v) => !$v->hasAllRequiredEquipment());


        return view('dashboard', compact(
            'totalVehicles',
            'openIssues',
            'upcomingDeadlines',
            'upcomingAppointments',
            'incompleteVehicles'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
