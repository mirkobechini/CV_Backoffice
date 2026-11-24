<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentRevision;

/**
 * Registrazione di una revisione/collaudo effettuato su un'attrezzatura:
 * crea la voce di storico e aggiorna la data corrente (+ relativa scadenza)
 * di quel tipo di controllo. Estratto da EquipmentCollaudoController per
 * essere riusabile anche dal completamento di un appuntamento attrezzature
 * (EquipmentMaintenanceCompletionService).
 */
class EquipmentRevisionService
{
    public function apply(Equipment $equipment, array $data): EquipmentRevision
    {
        $revision = $equipment->revisions()->create([
            'kind' => $data['kind'],
            'performed_date' => $data['performed_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        if ($data['kind'] === EquipmentRevision::KIND_COLLAUDO) {
            $update = EquipmentRevisionDateCalculator::resolveNextCollaudoDate([
                'collaudo_date' => $data['performed_date'],
                'equipment_type_id' => $equipment->equipment_type_id,
            ]);
            $equipment->update([
                'collaudo_date' => $data['performed_date'],
                'next_collaudo_date' => $update['next_collaudo_date'] ?? null,
            ]);
        } else {
            $update = EquipmentRevisionDateCalculator::resolveExpirationDate([
                'revision_date' => $data['performed_date'],
                'equipment_type_id' => $equipment->equipment_type_id,
            ]);
            $equipment->update([
                'revision_date' => $data['performed_date'],
                'expiration_date' => $update['expiration_date'] ?? null,
            ]);
        }

        return $revision;
    }
}
