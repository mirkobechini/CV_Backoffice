<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche di affidabilità/utilizzo flotta per la tab "Affidabilità e
 * utilizzo" di /admin/statistics (solo capi/sottocapi). Scoperte per
 * gruppo tramite Vehicle::forCurrentUser().
 */
class FleetUsageStatisticsService
{
    /**
     * Km percorsi per veicolo NEL periodo: differenza tra la lettura più
     * alta e quella più bassa registrate (serve almeno 2 letture distinte,
     * altrimenti il veicolo non ha dati sufficienti e viene escluso, non
     * mostrato a zero). Usato anche da CostStatisticsService::costPerKm():
     * lì il denominatore deve restare sempre una distanza percorsa, mai il
     * chilometraggio assoluto — per quello vedi currentMileageByVehicle().
     *
     * Righe (vehicle_id/label/km), non una mappa label => valore:
     * internal_code non è univoco (solo il formato a 4 cifre è vincolato),
     * quindi due veicoli diversi con lo stesso codice — es. un mezzo
     * dismesso sostituito con lo stesso numero di flotta — andrebbero in
     * collisione su una singola chiave, con uno che sovrascrive
     * silenziosamente i dati dell'altro invece di restare distinti.
     *
     * @return Collection<int, array{vehicle_id: int, label: string, km: int}>
     */
    public function kmTraveledByVehicle(?Carbon $since = null): Collection
    {
        $rows = MileageLog::forCurrentUser()
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since))
            ->selectRaw('vehicle_id, MIN(mileage) as min_mileage, MAX(mileage) as max_mileage, COUNT(*) as readings')
            ->groupBy('vehicle_id')
            ->get()
            ->filter(fn ($row) => $row->readings >= 2 && $row->max_mileage > $row->min_mileage);

        $labels = $this->labelByVehicle($rows->pluck('vehicle_id'));

        return $rows
            ->map(fn ($row) => [
                'vehicle_id' => $row->vehicle_id,
                'label' => $labels->get($row->vehicle_id, 'N/A'),
                'km' => $row->max_mileage - $row->min_mileage,
            ])
            ->sortByDesc('km')
            ->values();
    }

    /**
     * Chilometraggio ASSOLUTO per veicolo: l'ultima lettura (per log_date),
     * stesso criterio di Vehicle::getMileageAttribute()/latestMileageLog.
     * Usato dalla card "Km percorsi per veicolo" con filtro "Tutto": la
     * differenza MAX-MIN da quando il veicolo è tracciato nell'app sarebbe
     * inferiore al reale per un mezzo che aveva già km sul contachilometri
     * prima di iniziare a usarla, disallineato da quanto mostrato ovunque
     * altrove (scheda veicolo, PDF).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, km: int}>
     */
    public function currentMileageByVehicle(): Collection
    {
        return Vehicle::with('latestMileageLog')
            ->forCurrentUser()
            ->get()
            ->filter(fn (Vehicle $v) => $v->latestMileageLog !== null)
            ->map(fn (Vehicle $v) => [
                'vehicle_id' => $v->id,
                'label' => $v->internal_code ?? 'N/A',
                'km' => $v->latestMileageLog->mileage,
            ])
            ->sortByDesc('km')
            ->values();
    }

    /**
     * Classifica guasti per veicolo nel periodo (i 10 con più guasti):
     * "quale mezzo sta dando più pensieri", a differenza della matrice
     * guasti-per-parte (IssueStatisticsService::vehiclePartMatrix) qui
     * conta ogni guasto, anche quelli senza parte assegnata. Righe, non
     * una mappa label => valore: stesso motivo di kmTraveledByVehicle()
     * sopra (internal_code non è univoco).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, count: int}>
     */
    public function issueCountByVehicle(?Carbon $since = null): Collection
    {
        $counts = Issue::forCurrentUser()
            ->when($since, fn ($q) => $q->where('event_date', '>=', $since))
            ->get(['vehicle_id'])
            ->countBy('vehicle_id');

        $labels = $this->labelByVehicle($counts->keys());

        return $counts
            ->map(fn ($count, $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values();
    }

    /**
     * Giorni medi tra appuntamento e rientro, solo sugli interventi
     * completati (return_date valorizzata): quanto un'officina trattiene
     * mediamente il mezzo. Complessivo + spaccato per officina.
     *
     * @return array{overall: float|null, byProvider: Collection<string, array{avg_days: float, count: int}>}
     */
    public function averageDowntimeByProvider(?Carbon $since = null): array
    {
        $records = MaintenanceRecord::forCurrentUser()
            ->whereNotNull('return_date')
            ->when($since, fn ($q) => $q->where('appointment_date', '>=', $since))
            ->with('provider')
            ->get(['id', 'provider_id', 'appointment_date', 'return_date']);

        if ($records->isEmpty()) {
            return ['overall' => null, 'byProvider' => collect()];
        }

        $days = $records->map(fn (MaintenanceRecord $r) => $r->appointment_date->diffInDays($r->return_date));

        $byProvider = $records
            ->groupBy(fn (MaintenanceRecord $r) => $r->provider?->name ?? 'N/A')
            ->map(fn ($group) => [
                'avg_days' => round($group->avg(fn (MaintenanceRecord $r) => $r->appointment_date->diffInDays($r->return_date)), 1),
                'count' => $group->count(),
            ])
            ->sortByDesc('avg_days');

        return [
            'overall' => round($days->avg(), 1),
            'byProvider' => $byProvider,
        ];
    }

    /**
     * Etichette veicolo (internal_code) per un set di id, in un'unica
     * query — evita una query per veicolo nei metodi sopra.
     *
     * @param  Collection<int, int>  $vehicleIds
     * @return Collection<int, string> vehicle_id => internal_code
     */
    private function labelByVehicle(Collection $vehicleIds): Collection
    {
        return Vehicle::whereIn('id', $vehicleIds->unique())
            ->pluck('internal_code', 'id');
    }
}
