<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Vehicle;

class DocumentStatusController extends Controller
{
    /**
     * Tipologie di scadenza mostrate come colonna per ogni veicolo, nello
     * stesso ordine della tabella/form scadenze.
     */
    private const DEADLINE_TYPES = [
        Deadline::TYPE_ASSICURAZIONE,
        Deadline::TYPE_MINISTERIAL,
        Deadline::TYPE_OXYGEN,
        Deadline::TYPE_TAGLIANDO,
        Deadline::TYPE_CINGHIA,
    ];

    /**
     * Vista d'insieme sullo stato di documenti/scadenze della flotta: una
     * riga per veicolo con una colonna per ogni documento/scadenza, per
     * capire a colpo d'occhio cosa manca o sta per scadere senza aprire
     * ogni veicolo singolarmente. Le attrezzature restano in una sezione
     * separata (in previsione di un upload certificati dedicato, non
     * ancora implementato).
     */
    public function index()
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::with('brand', 'carModel')->forCurrentUser()->get();

        // Stesso raggruppamento "ultima occorrenza per veicolo+tipo" già
        // usato da DeadlineController::index(): l'intera flotta ha poche
        // decine di scadenze, una query con elaborazione in memoria evita
        // N query (una per veicolo+tipo) per costruire la matrice.
        $latestDeadlines = Deadline::with('vehicle')
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->get()
            ->sortBy(fn (Deadline $d) => [$d->is_renewed ? 1 : 0, $d->id * -1])
            ->unique(fn (Deadline $d) => $d->vehicle_id . '|' . $d->type)
            ->values();

        Deadline::syncStatusesFromRules($latestDeadlines);

        $deadlinesByVehicle = [];
        foreach ($latestDeadlines as $deadline) {
            $deadlinesByVehicle[$deadline->vehicle_id][$deadline->type] = $deadline;
        }

        // L'attrezzatura non assegnata a un veicolo non ha un gruppo
        // proprio: resta visibile a tutti, come nella dashboard/indice.
        $equipment = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')
                    ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->get();

        return view('admin.documents.index', [
            'vehicles' => $vehicles,
            'deadlinesByVehicle' => $deadlinesByVehicle,
            'equipment' => $equipment,
            'deadlineTypes' => self::DEADLINE_TYPES,
        ]);
    }
}
