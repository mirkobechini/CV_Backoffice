<?php

namespace App\Services;

use App\Models\FleetSetting;
use App\Models\Tire;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Calcola la stagionalità di gomme attesa (in base alle due date globali di
 * cambio) e quali veicoli della flotta sono in regola o in ritardo.
 *
 * Solo i veicoli che hanno almeno un set di gomme tracciato vengono
 * considerati: un veicolo senza alcuna gomma registrata non ha ancora
 * adottato la funzionalità e non deve comparire come "in ritardo".
 */
class TireSeasonService
{
    public function expectedSeason(?Carbon $date = null): string
    {
        $date = $date ?? Carbon::today();
        $settings = FleetSetting::current();

        $winterSwitch = $this->monthDayToCarbon($settings->winter_switch_date, $date->year);
        $summerSwitch = $this->monthDayToCarbon($settings->summer_switch_date, $date->year);

        if ($winterSwitch->lt($summerSwitch)) {
            // Caso limite: data di cambio invernale prima di quella estiva
            // nello stesso anno (impostazione insolita ma non impedita).
            return ($date->gte($winterSwitch) && $date->lt($summerSwitch))
                ? Tire::SEASON_WINTER
                : Tire::SEASON_SUMMER;
        }

        // Caso normale: cambio invernale (es. novembre) più avanti nell'anno
        // di quello estivo (es. aprile). "Invernale" copre quindi due
        // intervalli nello stesso anno solare: da winterSwitch a fine anno,
        // e da inizio anno a prima di summerSwitch.
        return ($date->gte($winterSwitch) || $date->lt($summerSwitch))
            ? Tire::SEASON_WINTER
            : Tire::SEASON_SUMMER;
    }

    /**
     * Veicoli con almeno un set di gomme tracciato la cui gomma montata
     * corrisponde alla stagionalità attesa (o è quattro stagioni).
     */
    public function compliantVehicles(?int $groupId = null): Collection
    {
        return $this->vehiclesWithTires($groupId)->filter(fn (Vehicle $vehicle) => $this->isCompliant($vehicle));
    }

    /**
     * Veicoli con almeno un set di gomme tracciato ma non ancora passati
     * alla stagionalità attesa.
     */
    public function pendingVehicles(?int $groupId = null): Collection
    {
        return $this->vehiclesWithTires($groupId)->reject(fn (Vehicle $vehicle) => $this->isCompliant($vehicle));
    }

    public function isCompliant(Vehicle $vehicle): bool
    {
        $mounted = $vehicle->tires->firstWhere('status', Tire::STATUS_MOUNTED);

        if (! $mounted) {
            return false;
        }

        return $mounted->season === $this->expectedSeason() || $mounted->season === Tire::SEASON_ALL_SEASON;
    }

    private function vehiclesWithTires(?int $groupId): Collection
    {
        return Vehicle::with('tires')
            ->forGroup($groupId)
            ->whereHas('tires')
            ->get();
    }

    private function monthDayToCarbon(string $monthDay, int $year): Carbon
    {
        [$month, $day] = array_map('intval', explode('-', $monthDay));

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
