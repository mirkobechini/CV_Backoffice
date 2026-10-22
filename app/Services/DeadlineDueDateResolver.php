<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

/**
 * Validazione tipo e calcolo della data di scadenza per
 * DeadlineService::createDeadline()/updateDeadline(). Estratto per tenere
 * DeadlineService sotto le 400 righe.
 */
class DeadlineDueDateResolver
{
    /**
     * @throws \RuntimeException
     */
    public static function validateOxygenForVehicle(array $data, Vehicle $vehicle): void
    {
        if (($data['type'] ?? null) === Deadline::TYPE_OXYGEN && ! Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            throw new \RuntimeException('La revisione impianto ossigeno è disponibile solo per le ambulanze.');
        }
    }

    /**
     * Calcola la data di scadenza in base al tipo.
     *
     * Per i tipi a calcolo automatico (ministeriale/ossigeno), una data
     * inserita esplicitamente in due_date ha sempre la precedenza sul
     * calcolo automatico: permette di correggere manualmente la data di
     * rinnovo (es. revisione fatta con anticipo/ritardo, dati storici),
     * lasciando il campo vuoto quando si preferisce il calcolo automatico.
     */
    public static function resolve(array $data, Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (in_array($data['type'] ?? '', [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
            return self::resolveManualDueDate($data['due_date'] ?? null);
        }

        if (! empty($data['due_date'])) {
            return self::resolveManualDueDate($data['due_date']);
        }

        if (($data['type'] ?? null) === Deadline::TYPE_MINISTERIAL) {
            return Deadline::calculateMinisterialDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        if (($data['type'] ?? null) === Deadline::TYPE_OXYGEN) {
            return Deadline::calculateOxygenDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        return self::resolveManualDueDate($data['due_date'] ?? null);
    }

    /**
     * Converte una stringa "Y-m" in data Carbon (fine mese), oppure "Y-m-d"
     * tenendo il giorno esatto: le assicurazioni rinnovano a una data
     * precisa (vedi Store/UpdateDeadlineRequest), le altre tipologie
     * restano a livello di mese/anno come da sempre.
     */
    private static function resolveManualDueDate(?string $dueDate): ?Carbon
    {
        if (! $dueDate) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return Carbon::createFromFormat('Y-m-d', $dueDate) ?: null;
        }

        // Giorno fissato a 1 esplicitamente: createFromFormat('Y-m', ...) da
        // solo lascia il giorno al valore corrente (es. 30 se oggi è il 30),
        // e per mesi più corti trabocca già al mese successivo PRIMA di
        // endOfMonth(), dando il risultato sbagliato.
        $parsedDate = Carbon::createFromFormat('Y-m-d', $dueDate.'-01');

        if (! $parsedDate) {
            return null;
        }

        return $parsedDate->endOfMonth();
    }
}
