<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

class DeadlineService
{
    /**
     * Tipi periodici per cui l'aggiunta di una nuova scadenza rinnova
     * automaticamente quella precedente dello stesso veicolo.
     */
    private const AUTO_RENEW_TYPES = [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN];

    /**
     * Crea una nuova scadenza con calcolo automatico della data.
     *
     * Per i tipi periodici (Revisione Ministeriale/Impianto Ossigeno), se il
     * veicolo ha già una scadenza dello stesso tipo non ancora rinnovata,
     * viene marcata automaticamente come rinnovata e collegata alla nuova
     * (renews_deadline_id), così da poterla ripristinare se la nuova viene
     * eliminata per errore.
     */
    public function createDeadline(array $data, Vehicle $vehicle): Deadline
    {
        $this->validateOxygenForVehicle($data, $vehicle);

        $dueDate = $this->resolveDueDate($data, $vehicle);

        if (! $dueDate) {
            throw new \RuntimeException('Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.');
        }

        $previousDeadline = in_array($data['type'], self::AUTO_RENEW_TYPES, true)
            ? $this->findCurrentDeadlineToRenew($vehicle, $data['type'])
            : null;

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => (bool) ($data['is_renewed'] ?? false),
            'renews_deadline_id' => $previousDeadline?->id,
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
        ]);

        $deadline->syncStatusFromRules();

        if ($previousDeadline) {
            $previousDeadline->update([
                'is_renewed' => true,
                'status' => Deadline::STATUS_RENEWED,
            ]);
        }

        return $deadline;
    }

    /**
     * Trova la scadenza "attuale" (non ancora rinnovata) dello stesso tipo
     * per il veicolo, quella che una nuova scadenza andrebbe a sostituire.
     */
    private function findCurrentDeadlineToRenew(Vehicle $vehicle, string $type): ?Deadline
    {
        return $vehicle->deadlines()
            ->where('type', $type)
            ->where('is_renewed', false)
            ->orderByDesc('due_date')
            ->first();
    }

    /**
     * Aggiorna una scadenza esistente con ricalcolo della data.
     *
     * Se la scadenza viene marcata come rinnovata (is_renewed) e appartiene a
     * un tipo con rinnovo periodico (ministeriale/ossigeno), crea
     * automaticamente la scadenza successiva. La data di rinnovo può essere
     * fornita (due_date) oppure calcolata in automatico.
     */
    public function updateDeadline(Deadline $deadline, array $data, Vehicle $vehicle): Deadline
    {
        $this->validateOxygenForVehicle($data, $vehicle);

        $isRenewed = (bool) ($data['is_renewed'] ?? false);

        // resolveDueDate() dà sempre la precedenza a una data esplicita,
        // sia in caso di rinnovo che di semplice correzione manuale;
        // altrimenti calcola la data in automatico per i tipi periodici.
        $dueDate = $this->resolveDueDate($data, $vehicle, $deadline->id);

        if (! $dueDate) {
            throw new \RuntimeException('Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.');
        }

        $deadline->update([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => $isRenewed,
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
        ]);

        $deadline->syncStatusFromRules();

        // Crea automaticamente la scadenza successiva per i tipi periodici
        // quando la scadenza corrente viene marcata come rinnovata.
        if ($isRenewed && in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO], true)) {
            $this->createNextDeadlineAfterRenewal($deadline, $vehicle);
        }

        return $deadline;
    }

    /**
     * Crea la scadenza successiva dopo un rinnovo, se non esiste già.
     */
    private function createNextDeadlineAfterRenewal(Deadline $renewedDeadline, Vehicle $vehicle): void
    {
        $nextDueDate = null;

        if ($renewedDeadline->type === Deadline::TYPE_MINISTERIAL) {
            $nextDueDate = Deadline::calculateMinisterialDueDateForVehicle($vehicle, $renewedDeadline->id);
        } elseif ($renewedDeadline->type === Deadline::TYPE_OXYGEN) {
            $nextDueDate = Deadline::calculateOxygenDueDateForVehicle($vehicle, $renewedDeadline->id);
        } elseif ($renewedDeadline->type === Deadline::TYPE_TAGLIANDO) {
            $nextDueDate = Deadline::calculateTagliandoDueDateForVehicle($vehicle, $renewedDeadline->id);
        }

        if (! $nextDueDate) {
            return;
        }

        Deadline::firstOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'type' => $renewedDeadline->type,
                'due_date' => $nextDueDate->toDateString(),
            ],
            [
                'status' => Deadline::STATUS_PENDING,
                'renews_deadline_id' => $renewedDeadline->id,
            ]
        );
    }

    /**
     * Verifica che il tipo ossigeno sia valido per il veicolo.
     *
     * @throws \RuntimeException
     */
    private function validateOxygenForVehicle(array $data, Vehicle $vehicle): void
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
    private function resolveDueDate(array $data, Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (in_array($data['type'] ?? '', [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
            return $this->resolveManualDueDate($data['due_date'] ?? null);
        }

        if (! empty($data['due_date'])) {
            return $this->resolveManualDueDate($data['due_date']);
        }

        if (($data['type'] ?? null) === Deadline::TYPE_MINISTERIAL) {
            return Deadline::calculateMinisterialDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        if (($data['type'] ?? null) === Deadline::TYPE_OXYGEN) {
            return Deadline::calculateOxygenDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        return $this->resolveManualDueDate($data['due_date'] ?? null);
    }

    /**
     * Converte una stringa "Y-m" in data Carbon (fine mese).
     */
    private function resolveManualDueDate(?string $dueDate): ?Carbon
    {
        if (! $dueDate) {
            return null;
        }

        $parsedDate = Carbon::createFromFormat('Y-m', $dueDate);

        if (! $parsedDate) {
            return null;
        }

        return $parsedDate->endOfMonth();
    }
}
