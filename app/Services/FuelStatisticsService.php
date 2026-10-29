<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche carburante per la tab "Carburante" di /admin/statistics (solo
 * capi/sottocapi). Scoperte per gruppo tramite FuelLog::forCurrentUser().
 */
class FuelStatisticsService
{
    private function baseQuery(?Carbon $since)
    {
        return FuelLog::forCurrentUser()
            ->when($since, fn ($q) => $q->where('log_date', '>=', $since));
    }

    /**
     * Litri e spesa totali per anno (anno del rifornimento), per vedere il
     * trend nel tempo.
     *
     * @return Collection<string, array{liters: float, cost: float}>
     */
    public function litersAndCostByYear(?Carbon $since = null): Collection
    {
        return $this->baseQuery($since)
            ->get(['log_date', 'liters', 'cost_total'])
            ->groupBy(fn (FuelLog $log) => $log->log_date->format('Y'))
            ->map(fn ($group) => [
                'liters' => (float) $group->sum('liters'),
                'cost' => (float) $group->sum('cost_total'),
            ])
            ->sortKeys();
    }

    /**
     * Litri e spesa totali per veicolo nel periodo, dal più rifornito.
     * Righe (vehicle_id/label/...), non una mappa label => valore: stesso
     * motivo di CostStatisticsService::costByVehicle() (internal_code non
     * è univoco).
     *
     * @return Collection<int, array{vehicle_id: int, label: string, liters: float, cost: float}>
     */
    public function litersAndCostByVehicle(?Carbon $since = null): Collection
    {
        $logs = $this->baseQuery($since)->get(['vehicle_id', 'liters', 'cost_total']);

        $totals = $logs->groupBy('vehicle_id')->map(fn ($group) => [
            'liters' => (float) $group->sum('liters'),
            'cost' => (float) $group->sum('cost_total'),
        ]);

        $labels = Vehicle::whereIn('id', $totals->keys())->pluck('internal_code', 'id');

        return $totals
            ->map(fn ($row, $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'liters' => $row['liters'],
                'cost' => $row['cost'],
            ])
            ->sortByDesc('liters')
            ->values();
    }

    /**
     * Litri e spesa totali per tipo di alimentazione del veicolo
     * (Vehicle::fuelTypeLabels()), per vedere quanto pesa benzina/gasolio/
     * ecc. sulla flotta. I veicoli senza alimentazione impostata finiscono
     * sotto "Non specificata" invece di sparire dal totale.
     *
     * @return Collection<int, array{fuel_type: ?string, label: string, liters: float, cost: float}>
     */
    public function litersAndCostByFuelType(?Carbon $since = null): Collection
    {
        $logs = $this->baseQuery($since)->with('vehicle')->get(['id', 'vehicle_id', 'liters', 'cost_total']);
        $labels = Vehicle::fuelTypeLabels();

        return $logs
            ->groupBy(fn (FuelLog $log) => $log->vehicle?->fuel_type ?? '')
            ->map(fn ($group, $fuelType) => [
                'fuel_type' => $fuelType ?: null,
                'label' => $fuelType !== '' ? ($labels[$fuelType] ?? $fuelType) : __('Non specificata'),
                'liters' => (float) $group->sum('liters'),
                'cost' => (float) $group->sum('cost_total'),
            ])
            ->sortByDesc('liters')
            ->values();
    }

    /**
     * Litri e spesa totali per tipo di veicolo (Ambulanza, Pulmino,
     * ecc.), per vedere quale categoria di mezzi pesa di più sulla
     * spesa carburante della flotta.
     *
     * @return Collection<string, array{liters: float, cost: float}> tipo veicolo => litri e costo totali
     */
    public function litersAndCostByVehicleType(?Carbon $since = null): Collection
    {
        $logs = $this->baseQuery($since)->with('vehicle.vehicleType')->get(['id', 'vehicle_id', 'liters', 'cost_total']);

        return $logs
            ->groupBy(fn (FuelLog $log) => $log->vehicle?->vehicleType?->name ?? 'N/A')
            ->map(fn ($group) => [
                'liters' => (float) $group->sum('liters'),
                'cost' => (float) $group->sum('cost_total'),
            ])
            ->sortByDesc(fn ($row) => $row['liters']);
    }

    /**
     * Consumo medio (km/L) per veicolo nel periodo: media dei consumi tra
     * rifornimenti consecutivi dello stesso veicolo (FuelLog::
     * consumptionKmPerLiter(), stesso calcolo dell'elenco rifornimenti),
     * considerando solo coppie entrambe dentro il periodo selezionato — un
     * rifornimento appena fuori dal periodo non viene usato come
     * "precedente", per non far dipendere il risultato da dati che
     * l'utente non sta guardando. Ordinato dal consumo peggiore (km/L più
     * basso) al migliore, per segnalare per prime le anomalie.
     *
     * @return Collection<int, array{vehicle_id: int, label: string, km_per_liter: float}>
     */
    public function averageConsumptionByVehicle(?Carbon $since = null): Collection
    {
        $logs = $this->baseQuery($since)
            ->orderBy('log_date')
            ->orderBy('id')
            ->get(['id', 'vehicle_id', 'log_date', 'liters', 'mileage']);

        $averages = $logs->groupBy('vehicle_id')->map(function ($logsForVehicle) {
            $previous = null;
            $consumptions = collect();

            foreach ($logsForVehicle as $log) {
                $consumption = FuelLog::consumptionKmPerLiter($log, $previous);
                if ($consumption !== null) {
                    $consumptions->push($consumption);
                }
                $previous = $log;
            }

            return $consumptions->isEmpty() ? null : round($consumptions->avg(), 2);
        })->filter(fn (?float $avg) => $avg !== null);

        $labels = Vehicle::whereIn('id', $averages->keys())->pluck('internal_code', 'id');

        return $averages
            ->map(fn (float $avg, int $vehicleId) => [
                'vehicle_id' => $vehicleId,
                'label' => $labels->get($vehicleId, 'N/A'),
                'km_per_liter' => $avg,
            ])
            ->sortBy('km_per_liter')
            ->values();
    }
}
