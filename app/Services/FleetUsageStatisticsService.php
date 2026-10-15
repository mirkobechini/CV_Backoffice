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
     * Km percorsi per veicolo nel periodo: differenza tra la lettura più
     * alta e quella più bassa registrate. Serve almeno 2 letture distinte
     * nel periodo per avere un intervallo, altrimenti il veicolo non ha
     * dati sufficienti e viene escluso (non mostrato a zero, sarebbe
     * fuorviante quanto un dato mancante).
     *
     * @return Collection<string, int> veicolo => km percorsi
     */
    public function kmTraveledByVehicle(?Carbon $since = null): Collection
    {
        $rows = MileageLog::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since))
            ->selectRaw('vehicle_id, MIN(mileage) as min_mileage, MAX(mileage) as max_mileage, COUNT(*) as readings')
            ->groupBy('vehicle_id')
            ->get()
            ->filter(fn ($row) => $row->readings >= 2 && $row->max_mileage > $row->min_mileage)
            ->keyBy('vehicle_id');

        $labels = $this->labelByVehicle($rows->keys());

        return $rows
            ->mapWithKeys(fn ($row, $vehicleId) => [
                $labels->get($vehicleId, 'N/A') => $row->max_mileage - $row->min_mileage,
            ])
            ->sortDesc();
    }

    /**
     * Classifica guasti per veicolo nel periodo (i 10 con più guasti):
     * "quale mezzo sta dando più pensieri", a differenza della matrice
     * guasti-per-parte (IssueStatisticsService::vehiclePartMatrix) qui
     * conta ogni guasto, anche quelli senza parte assegnata.
     *
     * @return Collection<string, int> veicolo => numero guasti
     */
    public function issueCountByVehicle(?Carbon $since = null): Collection
    {
        $counts = Issue::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->when($since, fn ($q) => $q->where('event_date', '>=', $since))
            ->get(['vehicle_id'])
            ->countBy('vehicle_id');

        $labels = $this->labelByVehicle($counts->keys());

        return $counts
            ->mapWithKeys(fn ($count, $vehicleId) => [$labels->get($vehicleId, 'N/A') => $count])
            ->sortDesc()
            ->take(10);
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
        $records = MaintenanceRecord::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
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
