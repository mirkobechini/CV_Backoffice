<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Tire;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Calcola la stagionalità di gomme attesa (in base alle due date di cambio
 * del gruppo del veicolo) e quali veicoli sono in regola o in ritardo.
 *
 * Le date sono per-gruppo, non globali: due associazioni diverse che
 * condividono la stessa installazione possono avere esigenze stagionali
 * diverse. Un veicolo senza gruppo (raro, dato limite) usa le date di
 * default.
 *
 * Solo i veicoli che hanno almeno un set di gomme tracciato vengono
 * considerati: un veicolo senza alcuna gomma registrata non ha ancora
 * adottato la funzionalità e non deve comparire come "in ritardo".
 */
class TireSeasonService
{
    public const DEFAULT_WINTER_SWITCH = '11-15';

    public const DEFAULT_SUMMER_SWITCH = '04-15';

    public function expectedSeasonForGroup(?Group $group, ?Carbon $date = null): string
    {
        $date = $date ?? Carbon::today();
        $winterSwitchDate = $group?->winter_switch_date ?? self::DEFAULT_WINTER_SWITCH;
        $summerSwitchDate = $group?->summer_switch_date ?? self::DEFAULT_SUMMER_SWITCH;

        $winterSwitch = $this->monthDayToCarbon($winterSwitchDate, $date->year);
        $summerSwitch = $this->monthDayToCarbon($summerSwitchDate, $date->year);

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

    public function expectedSeasonForVehicle(Vehicle $vehicle, ?Carbon $date = null): string
    {
        return $this->expectedSeasonForGroup($vehicle->group, $date);
    }

    /**
     * Veicoli del gruppo (con almeno un set di gomme tracciato) la cui
     * gomma montata corrisponde alla stagionalità attesa per quel gruppo
     * (o è quattro stagioni).
     */
    public function compliantVehicles(?int $groupId = null): Collection
    {
        return $this->vehiclesWithTires($groupId)->filter(fn (Vehicle $vehicle) => $this->isCompliant($vehicle));
    }

    /**
     * Veicoli del gruppo (con almeno un set di gomme tracciato) non ancora
     * passati alla stagionalità attesa per quel gruppo.
     */
    public function pendingVehicles(?int $groupId = null): Collection
    {
        return $this->vehiclesWithTires($groupId)->reject(fn (Vehicle $vehicle) => $this->isCompliant($vehicle));
    }

    /**
     * Un veicolo può avere più di una gomma "montata" contemporaneamente
     * (es. un set anteriore e uno posteriore tracciati separatamente): è in
     * regola solo se TUTTE quelle montate corrispondono alla stagionalità
     * attesa (o sono quattro stagioni).
     */
    public function isCompliant(Vehicle $vehicle): bool
    {
        $mounted = $vehicle->tires->where('status', Tire::STATUS_MOUNTED);

        if ($mounted->isEmpty()) {
            return false;
        }

        $expectedSeason = $this->expectedSeasonForVehicle($vehicle);

        return $mounted->every(fn (Tire $tire) => $tire->season === $expectedSeason || $tire->season === Tire::SEASON_ALL_SEASON);
    }

    private function vehiclesWithTires(?int $groupId): Collection
    {
        return Vehicle::with('tires', 'group')
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
