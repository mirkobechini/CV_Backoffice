<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;

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
            ->take(20)
            ->get();

        // Scadenze imminenti (prossimi 30 giorni)
        $upcomingDeadlines = Deadline::with('vehicle')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(30))
            ->orderBy('due_date')
            ->get();

        // Appuntamenti futuri
        $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
            ->whereNull('return_date')
            ->where('appointment_date', '>=', now())
            ->orderBy('appointment_date')
            ->take(5)
            ->get();

        // Veicoli con equipaggiamento incompleto
        $incompleteVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
            ->get()
            ->filter(fn($v) => !$v->hasAllRequiredEquipment());

        // Attrezzature in scadenza (prossimi 30 giorni) o già scadute
        $expiringEquipment = Equipment::with('vehicle')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->get();


        return view('dashboard', compact(
            'totalVehicles',
            'openIssues',
            'upcomingDeadlines',
            'upcomingAppointments',
            'incompleteVehicles',
            'expiringEquipment'
        ));
    }
}
