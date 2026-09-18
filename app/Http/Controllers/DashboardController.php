<?php

namespace App\Http\Controllers;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Services\DashboardCache;
use App\Services\TireSeasonService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(TireSeasonService $tireSeasonService)
    {
        // La cache va segmentata per gruppo: quasi nessuna delle query sotto
        // era filtrata per gruppo (solo incompleteVehicles lo era), e la
        // chiave era un'unica stringa globale condivisa da tutti — la prima
        // richiesta calcolava i dati (di TUTTI i gruppi) e li serviva a
        // chiunque altro visitasse la dashboard nei 5 minuti successivi,
        // indipendentemente dal proprio gruppo.
        $activeGroup = auth()->user()?->activeGroup();
        $groupId = $activeGroup?->id;
        $cacheKey = DashboardCache::key($groupId);

        $data = Cache::remember($cacheKey, 300, function () use ($groupId, $activeGroup, $tireSeasonService) {
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

            // appointment_date è una colonna date (senza ora): confrontarla
            // con now() (data+ora corrente) la escludeva se l'appuntamento
            // era oggi ma l'ora corrente aveva già superato mezzanotte, cioè
            // sempre — un appuntamento di oggi spariva dai "prossimi" non
            // appena passava la mezzanotte. today() confronta solo la data.
            $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->whereNull('return_date')
                ->where('appointment_date', '>=', Carbon::today())
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
                ->where('appointment_date', '<=', Carbon::today())
                ->count();

            $tireSeasonExpected = $tireSeasonService->expectedSeasonForGroup($activeGroup);
            $tireSeasonPending = $tireSeasonService->pendingVehicles($groupId);
            $tireSeasonCompliantCount = $tireSeasonService->compliantVehicles($groupId)->count();

            return compact(
                'totalVehicles',
                'openIssues',
                'upcomingDeadlines',
                'expiredDeadlines',
                'upcomingAppointments',
                'incompleteVehicles',
                'expiringEquipment',
                'inWorkshopCount',
                'tireSeasonExpected',
                'tireSeasonPending',
                'tireSeasonCompliantCount'
            );
        });

        return view('dashboard', $data);
    }
}
