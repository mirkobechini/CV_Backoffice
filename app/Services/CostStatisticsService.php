<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche di costo per la tab "Costi" di /admin/statistics (solo
 * capi/sottocapi). Scoperte per gruppo tramite Vehicle::forCurrentUser().
 */
class CostStatisticsService
{
    public function __construct(
        private readonly FleetUsageStatisticsService $usageStatistics,
        private readonly FuelStatisticsService $fuelStatistics,
    ) {}

    /**
     * Sotto questa soglia di km nel periodo, un veicolo non entra in
     * costPerKm(): con pochissimi km percorsi, anche una spesa piccola
     * produce un €/km enorme e fuorviante (es. un mezzo quasi fermo con
     * un solo intervento costoso).
     */
    public const MIN_KM_FOR_COST_PER_KM = 100;

    private function baseQuery(?Carbon $since)
    {
        return MaintenanceRecord::forCurrentUser()
            ->whereNotNull('cost')
            ->when($since, fn ($q) => $q->where('appointment_date', '>=', $since));
    }

    /**
     * Spesa manutenzione totale per tipo di veicolo, ordinata dalla più
     * costosa: se una categoria di mezzi costa sistematicamente più
     * delle altre, non solo il singolo veicolo più caro.
     *
     * @return Collection<string, float> tipo veicolo => costo totale
     */
    public function costByVehicleType(?Carbon $since = null): Collection
    {
        return $this->baseQuery($since)
            ->with('vehicle.vehicleType')
            ->get()
            ->groupBy(fn (MaintenanceRecord $r) => $r->vehicle?->vehicleType?->name ?? 'N/A')
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortDesc();
    }

    /**
     * Spesa totale per anno (anno dell'appuntamento), per vedere il
     * trend di spesa della flotta nel tempo.
     *
     * @return Collection<string, float> anno => costo totale
     */
    public function costByYear(?Carbon $since = null): Collection
    {
        return $this->baseQuery($since)
            ->get(['appointment_date', 'cost'])
            ->groupBy(fn (MaintenanceRecord $r) => $r->appointment_date->format('Y'))
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortKeys();
    }

    /**
     * Spesa totale per veicolo nel periodo, dal più costoso. Righe
     * (vehicle_id/label/cost), non una mappa label => valore:
     * internal_code non è univoco (solo il formato a 4 cifre è vincolato),
     * quindi due veicoli diversi con lo stesso codice andrebbero in
     * collisione su una singola chiave, con uno che sovrascrive
     * silenziosamente i dati dell'altro.
     *
     * @return Collection<int, array{vehicle_id: int, label: string, cost: float}>
     */
    public function costByVehicle(?Carbon $since = null): Collection
    {
        $records = $this->baseQuery($since)->get(['vehicle_id', 'cost']);

        $totals = $records->groupBy('vehicle_id')->map(fn ($group) => (float) $group->sum('cost'));

        $labels = Vehicle::whereIn('id', $totals->keys())->pluck('internal_code', 'id');

        return $totals
            ->map(fn ($cost, $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'cost' => $cost,
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /**
     * Spesa totale per officina nel periodo, dalla più costosa.
     *
     * @return Collection<string, float> officina => costo totale
     */
    public function costByProvider(?Carbon $since = null): Collection
    {
        return $this->baseQuery($since)
            ->with('provider')
            ->get(['provider_id', 'cost'])
            ->groupBy(fn (MaintenanceRecord $r) => $r->provider?->name ?? 'N/A')
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortDesc();
    }

    /**
     * Costo per km percorso, per veicolo: incrocia la spesa totale del
     * veicolo nel periodo con i km percorsi nello stesso periodo
     * (FleetUsageStatisticsService::kmTraveledByVehicle()) — quanto costa
     * davvero tenerlo in strada, più onesto del solo costo assoluto.
     * L'incrocio avviene per vehicle_id, non per label (internal_code non
     * è univoco — vedi costByVehicle()).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, per_km: float}>
     */
    public function costPerKm(?Carbon $since = null): Collection
    {
        $kmByVehicleId = $this->usageStatistics->kmTraveledByVehicle($since)->keyBy('vehicle_id');

        return $this->costByVehicle($since)
            ->filter(fn ($row) => ($kmByVehicleId->get($row['vehicle_id'])['km'] ?? 0) >= self::MIN_KM_FOR_COST_PER_KM)
            ->map(fn ($row) => [
                'vehicle_id' => $row['vehicle_id'],
                'label' => $row['label'],
                'per_km' => round($row['cost'] / $kmByVehicleId->get($row['vehicle_id'])['km'], 2),
            ])
            ->sortByDesc('per_km')
            ->values();
    }

    /**
     * Costo totale per km percorso, per veicolo: come costPerKm() ma
     * sommando anche la spesa carburante (FuelStatisticsService::
     * litersAndCostByVehicle()), non solo le manutenzioni — quanto costa
     * davvero tenere in strada il veicolo, non solo "quanto costano le
     * riparazioni". Un veicolo con manutenzioni ma senza alcuna spesa
     * carburante registrata nel periodo resta incluso (0 di carburante),
     * il contrario (solo carburante, nessuna manutenzione) idem: si somma
     * quello che c'è, non si esclude per mancanza dell'altra fonte.
     *
     * @return Collection<int, array{vehicle_id: int, label: string, per_km: float}>
     */
    public function totalCostPerKm(?Carbon $since = null): Collection
    {
        $kmByVehicleId = $this->usageStatistics->kmTraveledByVehicle($since)->keyBy('vehicle_id');
        $maintenanceCostByVehicleId = $this->costByVehicle($since)->keyBy('vehicle_id');
        $fuelCostByVehicleId = $this->fuelStatistics->litersAndCostByVehicle($since)->keyBy('vehicle_id');

        $vehicleIds = $maintenanceCostByVehicleId->keys()->merge($fuelCostByVehicleId->keys())->unique();

        return $vehicleIds
            ->filter(fn ($vehicleId) => ($kmByVehicleId->get($vehicleId)['km'] ?? 0) >= self::MIN_KM_FOR_COST_PER_KM)
            ->map(function ($vehicleId) use ($kmByVehicleId, $maintenanceCostByVehicleId, $fuelCostByVehicleId) {
                $totalCost = ($maintenanceCostByVehicleId->get($vehicleId)['cost'] ?? 0)
                    + ($fuelCostByVehicleId->get($vehicleId)['cost'] ?? 0);

                return [
                    'vehicle_id' => $vehicleId,
                    'label' => $maintenanceCostByVehicleId->get($vehicleId)['label']
                        ?? $fuelCostByVehicleId->get($vehicleId)['label']
                        ?? 'N/A',
                    'per_km' => round($totalCost / $kmByVehicleId->get($vehicleId)['km'], 2),
                ];
            })
            ->sortByDesc('per_km')
            ->values();
    }
}
