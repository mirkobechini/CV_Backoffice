<?php

namespace App\Services\CsvImport;

use App\Models\Vehicle;

abstract class AbstractCsvImporter implements CsvImporter
{
    /**
     * validate() risolve _vehicle_id scoperto per gruppo (forCurrentUser()),
     * ma import() lo riceve indietro solo come campo nascosto del form:
     * senza ricontrollarlo qui, un utente poteva alterarlo prima di
     * inviare la conferma e importare dati sul veicolo di un altro gruppo.
     */
    protected function vehicleBelongsToCurrentUser(mixed $vehicleId): bool
    {
        return Vehicle::forCurrentUser()->whereKey($vehicleId)->exists();
    }
}
