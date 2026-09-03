<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

class DeadlineService
{
    /**
     * Crea una nuova scadenza con calcolo automatico della data.
     */
    public function createDeadline(array $data, Vehicle $vehicle): Deadline
    {
        $this->validateOxygenForVehicle($data, $vehicle);

        $dueDate = $this->resolveDueDate($data, $vehicle);

        if (!$dueDate) {
            throw new \RuntimeException('Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.');
        }

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => (bool) ($data['is_renewed'] ?? false),
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
        ]);

        $deadline->syncStatusFromRules();

        return $deadline;
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

        // Se è un rinnovo con data esplicita, usiamo quella; altrimenti
        // calcoliamo la data in automatico (per i tipi periodici).
        if ($isRenewed && !empty($data['due_date'])) {
            $dueDate = $this->resolveManualDueDate($data['due_date']);
        } else {
            $dueDate = $this->resolveDueDate($data, $vehicle, $deadline->id);
        }

        if (!$dueDate) {
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
        if ($isRenewed && in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN], true)) {
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
        }

        if (!$nextDueDate) {
            return;
        }

        Deadline::firstOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'type' => $renewedDeadline->type,
                'due_date' => $nextDueDate->toDateString(),
            ],
            ['status' => Deadline::STATUS_PENDING]
        );
    }

    /**
     * Verifica che il tipo ossigeno sia valido per il veicolo.
     *
     * @throws \RuntimeException
     */
    private function validateOxygenForVehicle(array $data, Vehicle $vehicle): void
    {
        if (($data['type'] ?? null) === Deadline::TYPE_OXYGEN && !Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            throw new \RuntimeException('La revisione impianto ossigeno è disponibile solo per le ambulanze.');
        }
    }

    /**
     * Calcola la data di scadenza in base al tipo.
     */
    private function resolveDueDate(array $data, Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (in_array($data['type'] ?? '', [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
            return $this->resolveManualDueDate($data['due_date'] ?? null);
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
        if (!$dueDate) {
            return null;
        }

        $parsedDate = Carbon::createFromFormat('Y-m', $dueDate);

        if (!$parsedDate) {
            return null;
        }

        return $parsedDate->endOfMonth();
    }
}
