<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EquipmentMaintenanceCompletionService
{
    public function __construct(
        private readonly EquipmentRevisionService $equipmentRevisionService,
    ) {}

    /**
     * Completa un appuntamento attrezzature: imposta la data di
     * restituzione, chiude/riporta in lavorazione gli eventuali guasti
     * collegati e, per le sole attrezzature indicate come effettivamente
     * lavorate ($data['completed_equipment_ids']), registra la
     * revisione/collaudo se l'appuntamento è di quel tipo — stesso
     * meccanismo del completamento registrato manualmente
     * (EquipmentCollaudoController). Un appuntamento con 10 attrezzature
     * può chiuderne solo alcune: le altre restano collegate per lo storico,
     * senza alcuna revisione registrata (da riprogrammare altrove).
     */
    public function complete(EquipmentMaintenanceRecord $record, array $data): void
    {
        $record->loadMissing('issues', 'equipments');

        DB::transaction(function () use ($record, $data) {
            if (! $record->return_date) {
                $record->return_date = Carbon::today();
            }
            $record->save();

            // Un sì/no indipendente per ciascun guasto collegato
            // (data['resolved_issues'][$issue->id]), non più un'unica
            // scelta per tutti — stesso bug già corretto sul lato veicolo
            // nell'audit 2026-10-19, reintrodotto qui (audit 2026-11-25, B1).
            $record->issues->each(function (EquipmentIssue $issue) use ($data) {
                $resolved = (bool) ($data['resolved_issues'][$issue->id] ?? false);
                $issue->status = $resolved ? 'closed' : 'in_progress';
                $issue->save();
            });

            $this->applyEquipmentUpdates($record, $data['completed_equipment_ids'] ?? []);
        });
    }

    private function applyEquipmentUpdates(EquipmentMaintenanceRecord $record, array $completedEquipmentIds): void
    {
        if (empty($completedEquipmentIds)) {
            return;
        }

        $kind = match ($record->activity_type) {
            EquipmentMaintenanceRecord::ACTIVITY_REVISIONE => EquipmentRevision::KIND_REVISION,
            EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO => EquipmentRevision::KIND_COLLAUDO,
            default => null,
        };

        // Altri tipi di attività (riparazione, sostituzione, altro) non
        // hanno una scadenza periodica da far ripartire: la selezione resta
        // comunque valida (es. per future viste "cosa è stato fatto"), ma
        // non c'è nulla da aggiornare sull'attrezzatura.
        if (! $kind) {
            return;
        }

        $performedDate = $record->return_date;

        $record->equipments
            ->whereIn('id', $completedEquipmentIds)
            ->each(function (Equipment $equipment) use ($kind, $performedDate) {
                $this->equipmentRevisionService->apply($equipment, [
                    'kind' => $kind,
                    'performed_date' => $performedDate,
                ]);
            });
    }
}
