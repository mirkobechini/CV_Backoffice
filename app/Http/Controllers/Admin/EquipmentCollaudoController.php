<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkRecordEquipmentRevisionRequest;
use App\Http\Requests\StoreEquipmentRevisionRequest;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Services\EquipmentRevisionService;
use Illuminate\Support\Facades\DB;

/**
 * Registrazione di revisioni/collaudi effettuati sulle attrezzature, una
 * alla volta o in blocco. Estratto da EquipmentController per tenerlo sotto
 * le 400 righe (audit 2026-10-19, C12).
 */
class EquipmentCollaudoController extends Controller
{
    public function __construct(
        private readonly EquipmentRevisionService $equipmentRevisionService,
    ) {}

    /**
     * Registra una revisione o un collaudo effettuato: crea una voce di
     * storico e aggiorna la data corrente (+ la relativa scadenza) di
     * quel tipo di controllo sull'attrezzatura.
     */
    public function recordRevision(StoreEquipmentRevisionRequest $request, Equipment $equipment)
    {
        $this->authorize('update', $equipment);

        $data = $request->validated();
        $this->equipmentRevisionService->apply($equipment, $data);

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

        // Backstop oltre alla validazione già fatta in
        // BulkRecordEquipmentRevisionRequest::withValidator(): quel
        // controllo è oggi corretto, ma senza una verifica anche qui una
        // futura modifica alla FormRequest rimuoverebbe silenziosamente
        // l'isolamento di gruppo senza che nessun altro livello se ne
        // accorga (audit sicurezza 2026-11-25, A3).
        foreach ($equipments as $equipment) {
            $this->authorize('update', $equipment);
        }

        // Transazione unica: un fallimento a metà lascerebbe altrimenti
        // alcune attrezzature selezionate aggiornate e altre no, nella
        // stessa richiesta.
        DB::transaction(function () use ($equipments, $data) {
            foreach ($equipments as $equipment) {
                $this->equipmentRevisionService->apply($equipment, $data);
            }
        });

        $message = $data['kind'] === EquipmentRevision::KIND_COLLAUDO
            ? count($equipments).' collaudi registrati con successo.'
            : count($equipments).' revisioni registrate con successo.';

        return redirect()->route('admin.equipments.index')->with('status', $message);
    }
}
