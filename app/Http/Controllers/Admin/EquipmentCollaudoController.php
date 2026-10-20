<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkRecordEquipmentRevisionRequest;
use App\Http\Requests\StoreEquipmentRevisionRequest;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Services\EquipmentRevisionDateCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Registrazione di revisioni/collaudi effettuati sulle attrezzature, una
 * alla volta o in blocco. Estratto da EquipmentController per tenerlo sotto
 * le 400 righe (audit 2026-10-19, C12).
 */
class EquipmentCollaudoController extends Controller
{
    /**
     * Registra una revisione o un collaudo effettuato: crea una voce di
     * storico e aggiorna la data corrente (+ la relativa scadenza) di
     * quel tipo di controllo sull'attrezzatura.
     */
    public function recordRevision(StoreEquipmentRevisionRequest $request, Equipment $equipment)
    {
        $this->authorize('update', $equipment);

        $data = $request->validated();
        $this->applyRevision($equipment, $data);

        $message = $data['kind'] === EquipmentRevision::KIND_COLLAUDO
            ? 'Collaudo registrato con successo.'
            : 'Revisione registrata con successo.';

        return redirect()->route('admin.equipments.show', $equipment)->with('status', $message);
    }

    /**
     * Come recordRevision(), ma per più attrezzature insieme con la stessa
     * data: utile quando un gruppo di attrezzature (es. tutti gli estintori,
     * o tutte le barelle/sedie) viene revisionato lo stesso giorno, invece
     * di ripetere la stessa data un'attrezzatura alla volta.
     */
    public function bulkRecordRevision(BulkRecordEquipmentRevisionRequest $request)
    {
        $data = $request->validated();

        $equipments = Equipment::whereIn('id', $data['equipment_ids'])->get();

        // Transazione unica: un fallimento a metà lascerebbe altrimenti
        // alcune attrezzature selezionate aggiornate e altre no, nella
        // stessa richiesta.
        DB::transaction(function () use ($equipments, $data) {
            foreach ($equipments as $equipment) {
                $this->applyRevision($equipment, $data);
            }
        });

        $message = $data['kind'] === EquipmentRevision::KIND_COLLAUDO
            ? count($equipments).' collaudi registrati con successo.'
            : count($equipments).' revisioni registrate con successo.';

        return redirect()->route('admin.equipments.index')->with('status', $message);
    }

    /**
     * Crea la voce di storico e aggiorna la data corrente (+ la relativa
     * scadenza) di quel tipo di controllo sull'attrezzatura. Condivisa da
     * recordRevision() e bulkRecordRevision().
     */
    private function applyRevision(Equipment $equipment, array $data): void
    {
        $equipment->revisions()->create([
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
    }
}
