<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Cache::remember('dashboard.stats', 300, function () {
            $totalVehicles = Vehicle::count();

            $openIssues = Issue::with('vehicle')
                ->open()
                ->orderByDesc('event_date')
                ->take(20)
                ->get();

            $upcomingDeadlines = Deadline::with('vehicle')
                ->upcoming()
                ->get();

            $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
                ->whereNull('return_date')
                ->where('appointment_date', '>=', now())
                ->orderBy('appointment_date')
                ->take(5)
                ->get();

            $incompleteVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
                ->whereHas('vehicleType.equipmentTypes')
                ->get()
                ->filter(fn($v) => !$v->hasAllRequiredEquipment());

            $expiringEquipment = Equipment::with('vehicle')
                ->expiringSoon()
                ->get();

            return compact(
                'totalVehicles',
                'openIssues',
                'upcomingDeadlines',
                'upcomingAppointments',
                'incompleteVehicles',
                'expiringEquipment'
            );
        });

        return view('dashboard', $data);
    }
}
