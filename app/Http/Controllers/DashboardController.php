<?php

namespace App\Http\Controllers;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use App\Services\DashboardCache;
use App\Services\TireSeasonService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

            // Scadenze scadute e non ancora rinnovate. Filtriamo su
            // automatic_status (calcolato live da data/km), non sulla
            // colonna status persistita: quest'ultima viene risincronizzata
            // solo quando qualcuno crea/modifica una scadenza o visita
            // l'elenco scadenze (vedi Deadline::syncStatusesFromRules()) —
            // una scadenza il cui due_date passa senza che nessuno tocchi
            // quel record resta "pending"/"valid" in DB anche quando ormai
            // è scaduta, e la dashboard non la mostrava mai.
            $expiredDeadlines = Deadline::with('vehicle.latestMileageLog')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->where('is_renewed', false)
                ->get()
                ->filter(fn (Deadline $d) => $d->automatic_status === Deadline::STATUS_EXPIRED)
                ->sortBy('due_date')
                ->values();

            // Un appuntamento non ancora iniziato (scopeNotYetStarted) va
            // solo qui, mai anche in "In officina" sotto (scopeCurrentlyInWorkshop,
            // il suo esatto complemento) — altrimenti lo stesso
            // veicolo/appuntamento comparirebbe due volte in dashboard.
            $upcomingAppointments = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->whereNull('return_date')
                ->notYetStarted()
                ->orderBy('appointment_date')
                ->take(5)
                ->get();

            $incompleteVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
                ->forCurrentUser()
                ->whereHas('vehicleType.equipmentTypes')
                ->get()
                ->filter(fn ($v) => ! $v->hasAllRequiredEquipment());

            // L'attrezzatura non assegnata a un veicolo non ha un gruppo
            // proprio: resta inclusa, come nell'indice attrezzature.
            $expiringEquipment = Equipment::with('vehicle')
                ->where(function ($q) {
                    $q->whereDoesntHave('vehicle')
                        ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
                })
                ->expiringSoonOverall()
                ->get()
                ->sortBy('next_due_date')
                ->values();

            // Veicoli attualmente in officina: check-in avvenuto, non ancora rientrati.
            $inWorkshopRecords = MaintenanceRecord::with(['vehicle', 'provider'])
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->currentlyInWorkshop()
                ->orderBy('appointment_date')
                ->get();
            $inWorkshopCount = $inWorkshopRecords->count();

            // expectedSeasonWithWarning() anticipa la stagione attesa nei 30
            // giorni prima del cambio, cosi un veicolo non ancora passato
            // compare come "da cambiare" con un margine di preavviso invece
            // che il giorno esatto della scadenza. $tireSeasonOverdue
            // distingue i due casi per il badge: in preavviso (non ancora
            // scaduto davvero) vs dopo la scadenza reale (scaduto).
            $tireSeasonStrictExpected = $tireSeasonService->expectedSeasonForGroup($activeGroup);
            $tireSeasonExpected = $tireSeasonService->expectedSeasonWithWarning($activeGroup);
            $tireSeasonOverdue = $tireSeasonExpected === $tireSeasonStrictExpected;
            $tireSeasonPending = $tireSeasonService->pendingVehicles($groupId, $tireSeasonExpected);
            $tireSeasonCompliantCount = $tireSeasonService->compliantVehicles($groupId, $tireSeasonExpected)->count();
            $tireSeasonNextSwitch = $tireSeasonService->nextSwitch($activeGroup);

            // Premio totale delle polizze attive (una per veicolo, quella
            // non ancora rinnovata): stesso raggruppamento "ultima
            // occorrenza per veicolo" già usato per la vista dedicata
            // assicurazioni, qui limitato al solo totale per il riepilogo
            // in dashboard.
            $activeInsurancePremiumTotal = Deadline::with('insuranceCoverages')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->where('type', Deadline::TYPE_ASSICURAZIONE)
                ->where('is_renewed', false)
                ->get()
                ->sortByDesc('id')
                ->unique('vehicle_id')
                ->sum('insurance_premium_total');

            // "Lavato questo mese" conta solo gli appuntamenti di lavaggio
            // conclusi (return_date valorizzato), non quelli solo
            // programmati: un appuntamento ancora aperto non garantisce che
            // il veicolo sia stato lavato davvero.
            $carWashRecords = MaintenanceRecord::with('vehicle')
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->where('activity_type', MaintenanceRecord::ACTIVITY_WASH)
                ->whereNotNull('return_date')
                ->whereBetween('return_date', [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()])
                ->orderByDesc('return_date')
                ->get()
                ->unique('vehicle_id')
                ->values();

            $carWashTotal = $totalVehicles;
            $carWashedVehicleIds = $carWashRecords->pluck('vehicle_id');
            $carWashMissingVehicles = Vehicle::forCurrentUser()->get()
                ->reject(fn (Vehicle $v) => $carWashedVehicleIds->contains($v->id))
                ->values();
            $carWashWashedCount = $carWashedVehicleIds->count();

            return compact(
                'totalVehicles',
                'openIssues',
                'upcomingDeadlines',
                'expiredDeadlines',
                'upcomingAppointments',
                'incompleteVehicles',
                'expiringEquipment',
                'inWorkshopCount',
                'inWorkshopRecords',
                'tireSeasonExpected',
                'tireSeasonPending',
                'tireSeasonCompliantCount',
                'tireSeasonNextSwitch',
                'tireSeasonOverdue',
                'activeInsurancePremiumTotal',
                'carWashRecords',
                'carWashTotal',
                'carWashMissingVehicles',
                'carWashWashedCount'
            );
        });

        return view('dashboard', $data);
    }
}
