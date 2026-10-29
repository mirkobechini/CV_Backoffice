<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\TireChange;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche pneumatici per la tab "Pneumatici" di /admin/statistics (solo
 * capi/sottocapi). La spesa viene da MaintenanceRecord (activity_type =
 * Cambio Gomme, stesso posto in cui si registra il costo dell'intervento);
 * frequenza e km medi vengono da TireChange (storico cambi, registrato a
 * parte e non necessariamente legato a un appuntamento con costo — es. un
 * cambio stagionale tra set già posseduti).
 */
class TireStatisticsService
{
    private function maintenanceBaseQuery(?Carbon $since)
    {
        return MaintenanceRecord::forCurrentUser()
            ->where('activity_type', MaintenanceRecord::ACTIVITY_TIRE_CHANGE)
            ->whereNotNull('cost')
            ->when($since, fn ($q) => $q->where('appointment_date', '>=', $since));
    }

    private function changesBaseQuery(?Carbon $since)
    {
        return TireChange::forCurrentUser()
            ->when($since, fn ($q) => $q->where('changed_date', '>=', $since));
    }

    /**
     * Spesa totale cambio gomme per anno (anno dell'appuntamento).
     *
     * @return Collection<string, float>
     */
    public function costByYear(?Carbon $since = null): Collection
    {
        return $this->maintenanceBaseQuery($since)
            ->get(['appointment_date', 'cost'])
            ->groupBy(fn (MaintenanceRecord $r) => $r->appointment_date->format('Y'))
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortKeys();
    }

    /**
     * Spesa totale cambio gomme per veicolo nel periodo, dal più costoso.
     * Righe (vehicle_id/label/cost), non una mappa label => valore: stesso
     * motivo di CostStatisticsService::costByVehicle() (internal_code non
     * è univoco).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, cost: float}>
     */
    public function costByVehicle(?Carbon $since = null): Collection
    {
        $records = $this->maintenanceBaseQuery($since)->get(['vehicle_id', 'cost']);
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
     * Numero di cambi gomme registrati per veicolo nel periodo (da
     * TireChange, indipendente dal fatto che ci sia un costo associato).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, changes: int}>
     */
    public function changeFrequencyByVehicle(?Carbon $since = null): Collection
    {
        $changes = $this->changesBaseQuery($since)->get(['vehicle_id']);
        $totals = $changes->groupBy('vehicle_id')->map->count();
        $labels = Vehicle::whereIn('id', $totals->keys())->pluck('internal_code', 'id');

        return $totals
            ->map(fn ($count, $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'changes' => $count,
            ])
            ->sortByDesc('changes')
            ->values();
    }

    /**
     * Km medi percorsi tra due cambi gomme consecutivi dello stesso
     * veicolo, per veicolo: media dei delta di mileage_at_change tra
     * cambi consecutivi entrambi nel periodo selezionato (stessa logica di
     * FuelStatisticsService::averageConsumptionByVehicle() — un cambio
     * appena fuori dal periodo non viene usato come "precedente"). Richiede
     * che entrambi i cambi abbiano un chilometraggio registrato.
     *
     * @return Collection<int, array{vehicle_id: int, label: string, avg_km: int}>
     */
    public function averageKmBetweenChangesByVehicle(?Carbon $since = null): Collection
    {
        $changes = $this->changesBaseQuery($since)
            ->whereNotNull('mileage_at_change')
            ->orderBy('changed_date')
            ->orderBy('id')
            ->get(['id', 'vehicle_id', 'changed_date', 'mileage_at_change']);

        $averages = $changes->groupBy('vehicle_id')->map(function ($changesForVehicle) {
            $previous = null;
            $deltas = collect();

            foreach ($changesForVehicle as $change) {
                if ($previous !== null) {
                    $km = $change->mileage_at_change - $previous->mileage_at_change;
                    if ($km > 0) {
                        $deltas->push($km);
                    }
                }
                $previous = $change;
            }

            return $deltas->isEmpty() ? null : (int) round($deltas->avg());
        })->filter(fn (?int $avg) => $avg !== null);

        $labels = Vehicle::whereIn('id', $averages->keys())->pluck('internal_code', 'id');

        return $averages
            ->map(fn (int $avgKm, int $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'avg_km' => $avgKm,
            ])
            ->sortByDesc('avg_km')
            ->values();
    }
}
