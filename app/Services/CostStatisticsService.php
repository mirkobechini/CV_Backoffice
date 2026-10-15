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
        return MaintenanceRecord::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->whereNotNull('cost')
            ->when($since, fn ($q) => $q->where('appointment_date', '>=', $since));
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
     * Spesa totale per veicolo nel periodo, dal più costoso.
     *
     * @return Collection<string, float> veicolo => costo totale
     */
    public function costByVehicle(?Carbon $since = null): Collection
    {
        $records = $this->baseQuery($since)->get(['vehicle_id', 'cost']);

        $totals = $records->groupBy('vehicle_id')->map(fn ($group) => (float) $group->sum('cost'));

        $labels = Vehicle::whereIn('id', $totals->keys())->pluck('internal_code', 'id');

        return $totals
            ->mapWithKeys(fn ($cost, $vehicleId) => [$labels->get($vehicleId, 'N/A') => $cost])
            ->sortDesc();
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
     *
     * @return Collection<string, float> veicolo => €/km
     */
    public function costPerKm(?Carbon $since = null): Collection
    {
        $costByVehicleLabel = $this->costByVehicle($since);
        $kmByVehicleLabel = $this->usageStatistics->kmTraveledByVehicle($since);

        return $costByVehicleLabel
            ->filter(fn ($cost, $label) => ($kmByVehicleLabel->get($label) ?? 0) >= self::MIN_KM_FOR_COST_PER_KM)
            ->map(fn ($cost, $label) => round($cost / $kmByVehicleLabel->get($label), 2))
            ->sortDesc();
    }
}
