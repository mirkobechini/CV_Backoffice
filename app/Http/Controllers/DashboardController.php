<?php

namespace App\Http\Controllers;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
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
        // La cache va segmentata per gruppo: quasi nessuna delle query sotto
        // era filtrata per gruppo (solo incompleteVehicles lo era), e la
        // chiave era un'unica stringa globale condivisa da tutti — la prima
        // richiesta calcolava i dati (di TUTTI i gruppi) e li serviva a
        // chiunque altro visitasse la dashboard nei 5 minuti successivi,
        // indipendentemente dal proprio gruppo.
        $groupId = auth()->user()?->activeGroup()?->id ?? 'none';
        $cacheKey = "dashboard.stats.{$groupId}";

        $data = Cache::remember($cacheKey, 300, function () {
            $totalVehicles = Vehicle::forCurrentUser()->count();

            $openIssues = Issue::with('vehicle')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->open()
                ->orderByDesc('event_date')
                ->take(20)
                ->get();

            $upcomingDeadlines = Deadline::with('vehicle')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->upcoming()
                ->get();

            // Scadenze scadute e non ancora rinnovate
            $expiredDeadlines = Deadline::with('vehicle')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->where('status', Deadline::STATUS_EXPIRED)
                ->where('is_renewed', false)
                ->orderBy('due_date')
                ->get();

            $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->whereNull('return_date')
                ->where('appointment_date', '>=', now())
                ->orderBy('appointment_date')
                ->take(5)
                ->get();

            $incompleteVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
                ->forCurrentUser()
                ->whereHas('vehicleType.equipmentTypes')
                ->get()
                ->filter(fn($v) => ! $v->hasAllRequiredEquipment());

            // L'attrezzatura non assegnata a un veicolo non ha un gruppo
            // proprio: resta inclusa, come nell'indice attrezzature.
            $expiringEquipment = Equipment::with('vehicle')
                ->where(function ($q) {
                    $q->whereDoesntHave('vehicle')
                        ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
                })
                ->expiringSoon()
                ->get();

            // Veicoli attualmente in officina: check-in avvenuto, non ancora rientrati.
            $inWorkshopCount = MaintenanceRecord::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->whereNull('return_date')
                ->where('appointment_date', '<=', now())
                ->count();

            return compact(
                'totalVehicles',
                'openIssues',
                'upcomingDeadlines',
                'expiredDeadlines',
                'upcomingAppointments',
                'incompleteVehicles',
                'expiringEquipment',
                'inWorkshopCount'
            );
        });

        return view('dashboard', $data);
    }
}
