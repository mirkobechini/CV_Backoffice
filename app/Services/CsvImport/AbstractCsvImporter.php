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

    /**
     * Risolve un riferimento veicolo (sigla o targa) per gruppo.
     * internal_code non è univoco (solo il formato a 4 cifre è vincolato),
     * quindi se il riferimento è una sigla potrebbero esserci più
     * corrispondenze: un ->first() silenzioso importerebbe i dati sul
     * veicolo sbagliato senza che nessuno se ne accorga. license_plate è
     * invece univoco, quindi un match su targa non è mai ambiguo.
     *
     * @return array{vehicle: ?Vehicle, ambiguous: bool}
     */
    protected function resolveVehicleByRef(string $vehicleRef): array
    {
        $matches = Vehicle::where(fn ($q) => $q->where('internal_code', $vehicleRef)
            ->orWhere('license_plate', $vehicleRef))
            ->forCurrentUser()
            ->get();

        return [
            'vehicle' => $matches->count() === 1 ? $matches->first() : null,
            'ambiguous' => $matches->count() > 1,
        ];
    }
}
