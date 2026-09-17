<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\DetectsDuplicates;
use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceRecordRequest;
use App\Http\Requests\UpdateMaintenanceRecordRequest;
use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Services\DeadlineService;
use App\Services\MileageLogService;
use App\Services\TireChangeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceRecordController extends Controller
{
    use DetectsDuplicates;
    use SortableAndGroupable;

    public function __construct(
        private readonly DeadlineService $deadlineService,
        private readonly MileageLogService $mileageLogService,
        private readonly TireChangeService $tireChangeService,
    ) {
        $this->authorizeResource(MaintenanceRecord::class, 'maintenanceRecord');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:vehicle,description,date',
            'sort_by' => 'nullable|in:vehicle,description,date',
            'sort_dir' => 'nullable|in:asc,desc',
            'status_filter' => 'nullable|in:all,scheduled,completed,with_issues',
            'vehicle_id' => 'nullable|integer',
            'q' => 'nullable|string|max:255',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');
        $statusFilter = $validated['status_filter'] ?? 'all';
        $vehicleFilter = $validated['vehicle_id'] ?? null;
        $q = $validated['q'] ?? null;

        $query = MaintenanceRecord::with(['vehicle.brand', 'vehicle.carModel', 'provider', 'items.itemable'])
            ->whereHas('vehicle', fn($vq) => $vq->forCurrentUser());

        $query->when($statusFilter === 'scheduled', fn($qr) => $qr->whereNull('return_date'))
            ->when($statusFilter === 'completed', fn($qr) => $qr->whereNotNull('return_date'))
            ->when(
                $statusFilter === 'with_issues',
                fn($qr) => $qr->whereHas('items', fn($iq) => $iq->where('itemable_type', Issue::class))
            );

        $query->when($vehicleFilter, fn($qr) => $qr->where('vehicle_id', $vehicleFilter));

        $query->when($q, function ($qr) use ($q) {
            $qr->where(function ($sub) use ($q) {
                $sub->whereHas('vehicle', function ($vq) use ($q) {
                    $vq->where('internal_code', 'like', "%{$q}%")
                        ->orWhere('license_plate', 'like', "%{$q}%");
                })
                    ->orWhere('activity_type', 'like', "%{$q}%")
                    ->orWhereHas('items', function ($iq) use ($q) {
                        $iq->whereHasMorph('itemable', [Issue::class], function ($mq) use ($q) {
                            $mq->where('description', 'like', "%{$q}%");
                        });
                    })
                    ->orWhereHas('items', function ($iq) use ($q) {
                        $iq->whereHasMorph('itemable', [Deadline::class], function ($mq) use ($q) {
                            $mq->where('type', 'like', "%{$q}%");
                        });
                    });
            });
        });

        $maintenanceRecords = $this->applySorting($query, $sortBy, $sortDir, [
            'vehicle' => fn(MaintenanceRecord $r) => $r->vehicle?->internal_code ?? '',
            'description' => fn(MaintenanceRecord $r) => $this->itemDescriptions($r) !== '' ? $this->itemDescriptions($r) : ($r->activity_type ?? ''),
            'date' => 'appointment_date',
        ]);

        $groupedMaintenanceRecords = $this->applyGrouping($maintenanceRecords, $groupBy, function (MaintenanceRecord $record) use ($groupBy) {
            return match ($groupBy) {
                'vehicle' => $record->vehicle?->internal_code ?? 'N/A',
                'description' => $this->itemDescriptions($record) !== '' ? $this->itemDescriptions($record) : ($record->activity_type ?? 'N/A'),
                'date' => $record->appointment_date
                    ? ucfirst($record->appointment_date->locale('it')->translatedFormat('F Y'))
                    : 'N/A',
            };
        });

        $vehicles = Vehicle::forCurrentUser()->orderBy('internal_code')->get(['id', 'internal_code']);

        return view('admin.maintenance-records.index', compact('maintenanceRecords', 'groupBy', 'sortBy', 'sortDir', 'groupedMaintenanceRecords', 'statusFilter', 'vehicleFilter', 'vehicles') + [
            'groupToggleUrl' => fn($f) => $this->groupToggleUrl($f, $groupBy, 'admin.maintenance-records.index'),
            'sortToggleUrl' => fn($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.maintenance-records.index'),
            'sortIcon' => fn($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Default: nessuna preselezione, utile quando apro la create manualmente.
        $preselectedIssueId = null;
        $preselectedVehicleId = null;
        $preselectedActivityType = null;

        // Accettiamo sia i nuovi parametri (issue_id, vehicle_id)
        // sia i vecchi alias (issue, vehicle) per retrocompatibilità.
        $rawIssueId = $request->query('issue_id', $request->query('issue'));
        $rawVehicleId = $request->query('vehicle_id', $request->query('vehicle'));

        // Sanitizzazione minima: consideriamo validi solo ID numerici.
        $issueId = is_scalar($rawIssueId) && ctype_digit((string) $rawIssueId)
            ? (int) $rawIssueId
            : null;

        $vehicleId = is_scalar($rawVehicleId) && ctype_digit((string) $rawVehicleId)
            ? (int) $rawVehicleId
            : null;

        // Se arriva un issue_id, il guasto è la fonte di verità:
        // ricarichiamo dal DB e preselezioniamo anche il veicolo collegato.
        if ($issueId !== null) {
            $issue = Issue::query()
                ->where('id', $issueId)
                ->whereIn('status', ['open', 'in_progress'])
                ->first();

            if ($issue) {
                $preselectedIssueId = $issue->id;
                $preselectedVehicleId = $issue->vehicle_id;
            }
            // Se non c'è un guasto valido, possiamo comunque preimpostare il veicolo.
        } elseif ($vehicleId !== null && Vehicle::where('id', $vehicleId)->exists()) {
            $preselectedVehicleId = $vehicleId;
        }

        $rawActivityType = $request->query('activity_type');
        if (is_string($rawActivityType) && in_array($rawActivityType, MaintenanceRecord::ACTIVITY_TYPES, true)) {
            $preselectedActivityType = $rawActivityType;
        }

        $vehicles = Vehicle::forCurrentUser()->get();
        $providers = Provider::all();
        // Guasti aperti o in lavorazione: selezionabili per nuovi appuntamenti.
        // Includiamo anche 'in_progress' così un guasto non risolto in un appuntamento
        // precedente resta selezionabile per un nuovo appuntamento.
        $openIssues = Issue::whereIn('status', ['open', 'in_progress'])->get(['id', 'vehicle_id', 'description', 'event_date']);
        // Guasti risolti: per registrare riparazioni/appuntamenti già avvenuti.
        // Escludiamo quelli già collegati a un appuntamento.
        $closedIssues = Issue::where('status', 'closed')
            ->whereDoesntHave('maintenanceRecordItems')
            ->get(['id', 'vehicle_id', 'description', 'event_date']);

        // Una sola deadline per tipo per veicolo: prendiamo l'ultima non rinnovata
        $pendingDeadlines = Deadline::whereIn('status', ['pending', 'expired', 'valid'])
            ->orderByDesc('due_date')
            ->get()
            ->unique(function ($item) {
                return $item->vehicle_id . '-' . $item->type;
            })
            ->values();

        // Set di gomme "in magazzino" proponibili per un appuntamento di tipo
        // "Cambio Gomme": filtrati per veicolo lato client, come guasti/scadenze.
        $storedTires = Tire::where('status', Tire::STATUS_STORED)
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->get(['id', 'vehicle_id', 'season', 'axle', 'brand', 'model_name', 'size']);

        // La view usa old(..., $preselected...) così old() ha priorità
        // dopo un errore validazione, altrimenti usa le preselezioni.
        return view('admin.maintenance-records.create', compact('vehicles', 'providers', 'openIssues', 'closedIssues', 'pendingDeadlines', 'storedTires', 'preselectedIssueId', 'preselectedVehicleId', 'preselectedActivityType'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMaintenanceRecordRequest $request)
    {
        $data = $request->validated();

        $duplicateRecord = $this->findDuplicate(MaintenanceRecord::class, [
            'vehicle_id' => $data['vehicle_id'],
            'provider_id' => $data['provider_id'],
            'appointment_date' => $data['appointment_date'],
            'return_date' => $data['return_date'] ?? null,
            'activity_type' => $data['activity_type'] ?? null,
        ]);

        if ($duplicateRecord) {
            return redirect()
                ->route('admin.maintenance-records.show', $duplicateRecord->id)
                ->with('status', 'Intervento già registrato: creazione duplicata bloccata.');
        }

        $newRecord = MaintenanceRecord::create([
            'vehicle_id' => $data['vehicle_id'],
            'provider_id' => $data['provider_id'],
            'appointment_date' => $data['appointment_date'],
            'return_date' => $data['return_date'] ?? null,
            'activity_type' => $data['activity_type'] ?? null,
            'mileage_at_service' => $data['mileage_at_service'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $completedIssueIds = $data['completed_issue_ids'] ?? [];
        $completedDeadlineIds = $data['completed_deadline_ids'] ?? [];

        if (! empty($data['issue_ids'])) {
            foreach ($data['issue_ids'] as $issueId) {
                $newRecord->items()->create([
                    'itemable_id' => $issueId,
                    'itemable_type' => Issue::class,
                    'completed' => in_array((string) $issueId, $completedIssueIds, true),
                ]);
                // Il guasto passa automaticamente in lavorazione
                Issue::where('id', $issueId)->where('status', 'open')->update(['status' => 'in_progress']);
            }
        }
        if (! empty($data['deadline_ids'])) {
            foreach ($data['deadline_ids'] as $deadlineId) {
                $newRecord->items()->create([
                    'itemable_id' => $deadlineId,
                    'itemable_type' => Deadline::class,
                    'completed' => in_array((string) $deadlineId, $completedDeadlineIds, true),
                ]);
            }
        }

        $this->linkTireItems($newRecord, $data);

        // Se la data di rientro è compilata, l'appuntamento è considerato
        // completato: aggiorna i guasti e rinnova le scadenze marcate come
        // completate, partendo dalla data di rientro.
        if ($newRecord->return_date) {
            $this->processCompletedItems($newRecord, $completedIssueIds, $completedDeadlineIds);
        }

        return redirect()->route('admin.maintenance-records.show', $newRecord->id)->with('status', 'Intervento aggiunto con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->load(['vehicle', 'provider', 'items.itemable']);

        return view('admin.maintenance-records.show', compact('maintenanceRecord'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->load(['vehicle', 'provider', 'items.itemable']);

        $vehicles = Vehicle::forCurrentUser()->get();
        $providers = Provider::all();
        // In edit rendiamo selezionabili i guasti attivi + quelli già collegati al record.
        $linkedIssueIds = $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id');
        $openIssues = Issue::whereIn('status', ['open'])
            ->orWhereIn('id', $linkedIssueIds)
            ->get(['id', 'vehicle_id', 'description', 'status', 'event_date']);

        // Guasti risolti non ancora collegati: selezionabili per registrare
        // riparazioni già avvenute anche in modifica.
        $closedIssues = Issue::where('status', 'closed')
            ->whereDoesntHave('maintenanceRecordItems')
            ->get(['id', 'vehicle_id', 'description', 'event_date']);

        $linkedDeadlineIds = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->pluck('itemable_id');
        $pendingDeadlines = Deadline::whereIn('status', ['pending', 'expired', 'valid'])
            ->orWhereIn('id', $linkedDeadlineIds)
            ->orderByDesc('due_date')
            ->get(['id', 'vehicle_id', 'type', 'due_date'])
            // Una sola per veicolo+tipo (mantenendo quelle già collegate)
            ->unique(function ($item) {
                return $item->vehicle_id . '-' . $item->type;
            })
            ->values();

        // Set collegati (se presenti) + gli altri set in magazzino del
        // veicolo, per poterli mantenere selezionati in modifica.
        $linkedTireIds = $maintenanceRecord->items
            ->where('itemable_type', Tire::class)
            ->pluck('itemable_id');
        $storedTires = Tire::where(function ($q) use ($linkedTireIds) {
            $q->where('status', Tire::STATUS_STORED);
            if ($linkedTireIds->isNotEmpty()) {
                $q->orWhereIn('id', $linkedTireIds);
            }
        })
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->get(['id', 'vehicle_id', 'season', 'axle', 'brand', 'model_name', 'size']);

        return view('admin.maintenance-records.edit', compact('maintenanceRecord', 'vehicles', 'providers', 'openIssues', 'closedIssues', 'pendingDeadlines', 'storedTires', 'linkedTireIds'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaintenanceRecordRequest $request, MaintenanceRecord $maintenanceRecord)
    {
        $data = $request->validated();

        $maintenanceRecord->update([
            'vehicle_id' => $data['vehicle_id'],
            'provider_id' => $data['provider_id'],
            'appointment_date' => $data['appointment_date'],
            'return_date' => $data['return_date'] ?? null,
            'activity_type' => $data['activity_type'] ?? null,
            'mileage_at_service' => $data['mileage_at_service'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Sincronizza gli item: cancella e ricrea
        // Prima di cancellare, registra i guasti attualmente collegati
        $oldIssueIds = $maintenanceRecord->items()
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id')
            ->toArray();

        $maintenanceRecord->items()->delete();

        $completedIssueIds = $data['completed_issue_ids'] ?? [];
        $completedDeadlineIds = $data['completed_deadline_ids'] ?? [];

        $newIssueIds = [];
        if (! empty($data['issue_ids'])) {
            foreach ($data['issue_ids'] as $issueId) {
                $maintenanceRecord->items()->create([
                    'itemable_id' => $issueId,
                    'itemable_type' => Issue::class,
                    'completed' => in_array((string) $issueId, $completedIssueIds, true),
                ]);
                // Il guasto nuovo passa in lavorazione
                Issue::where('id', $issueId)->where('status', 'open')->update(['status' => 'in_progress']);
            }
            $newIssueIds = $data['issue_ids'];
        }

        // I guasti rimossi tornano in open
        $removedIssueIds = array_diff($oldIssueIds, $newIssueIds);
        if (! empty($removedIssueIds)) {
            Issue::whereIn('id', $removedIssueIds)
                ->where('status', 'in_progress')
                ->update(['status' => 'open']);
        }
        if (! empty($data['deadline_ids'])) {
            foreach ($data['deadline_ids'] as $deadlineId) {
                $maintenanceRecord->items()->create([
                    'itemable_id' => $deadlineId,
                    'itemable_type' => Deadline::class,
                    'completed' => in_array((string) $deadlineId, $completedDeadlineIds, true),
                ]);
            }
        }

        $this->linkTireItems($maintenanceRecord, $data);

        // Se la data di rientro è compilata, processa gli item completati
        if ($maintenanceRecord->return_date) {
            $this->processCompletedItems($maintenanceRecord, $completedIssueIds, $completedDeadlineIds);
        }

        return redirect()->route('admin.maintenance-records.show', $maintenanceRecord->id)->with('status', 'Intervento aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $this->authorize('delete', $maintenanceRecord);

        // Come in DeadlineController::destroy: se "back" punta alla show
        // dell'appuntamento appena eliminato, il redirect darebbe 404.
        $showUrl = route('admin.maintenance-records.show', $maintenanceRecord);

        $maintenanceRecord->loadMissing('items.itemable');

        // I guasti in lavorazione tornano in open
        $issueIds = $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id');
        if ($issueIds->isNotEmpty()) {
            Issue::whereIn('id', $issueIds)
                ->where('status', 'in_progress')
                ->update(['status' => 'open']);
        }

        // Ripristina lo stato precedente delle scadenze rinnovate da questo
        // appuntamento: elimina la scadenza successiva creata dal rinnovo e
        // riporta quella originale a pending.
        $restoredDeadlines = [];
        $renewedDeadlines = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->map(fn($item) => $item->itemable)
            ->filter()
            ->filter(fn($d) => $d->is_renewed);

        foreach ($renewedDeadlines as $deadline) {
            // La scadenza successiva creata dal rinnovo è quella collegata
            // tramite renews_deadline_id (impostato uniformemente da
            // DeadlineService::createNextOccurrence per tutti i tipi,
            // cinghia inclusa). Prima veniva ricercata ricalcolando la data
            // attesa e cercando una corrispondenza esatta: fragile, non
            // copriva la cinghia, e poteva non trovare/cancellare nulla se
            // il ricalcolo non tornava esattamente la stessa data.
            Deadline::where('renews_deadline_id', $deadline->id)->delete();

            // Riporta la scadenza originale a pending
            $deadline->status = Deadline::STATUS_PENDING;
            $deadline->is_renewed = false;
            $deadline->save();

            $restoredDeadlines[] = $deadline->type;
        }

        // Elimina gli item della pivot prima del soft-delete
        $maintenanceRecord->items()->delete();
        $maintenanceRecord->delete();

        $message = 'Intervento eliminato con successo.';
        if (! empty($restoredDeadlines)) {
            $message .= ' Ripristinate le scadenze: ' . implode(', ', array_unique($restoredDeadlines)) . '.';
        }

        $back = $request->input('back');
        if ($back && ! str_starts_with($back, $showUrl)) {
            return redirect($back)->with('status', $message);
        }

        return redirect()->route('admin.maintenance-records.index')->with('status', $message);
    }

    // --- CUSTOM METHOD ---
    // Metodo per completare un intervento e aggiornare lo stato del guasto associato
    public function complete(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $this->authorize('update', $maintenanceRecord);

        $maintenanceRecord->loadMissing(['items.itemable', 'vehicle.vehicleType']);

        $tireItems = $maintenanceRecord->items->where('itemable_type', Tire::class);

        $rules = [
            'issue_resolved' => 'required|boolean',
        ];
        $messages = [
            'issue_resolved.required' => 'Seleziona se il guasto è stato risolto o meno.',
            'issue_resolved.boolean' => 'Il valore selezionato non è valido.',
        ];

        if ($tireItems->isNotEmpty()) {
            $rules['previous_disposition'] = 'required|in:stored,retired';
            $messages['previous_disposition.required'] = 'Indica cosa fare delle gomme sostituite.';
            $messages['previous_disposition.in'] = 'La scelta per le gomme sostituite non è valida.';
        }

        $data = $request->validate($rules, $messages);

        // Se non c'è già una data di rientro, ne verrà impostata una automaticamente
        // (oggi): impedisce di completare un appuntamento non ancora avvenuto, il che
        // creerebbe una data di rientro precedente alla data di appuntamento.
        if (! $maintenanceRecord->return_date && $maintenanceRecord->appointment_date && Carbon::today()->lt($maintenanceRecord->appointment_date)) {
            return redirect()
                ->back()
                ->withErrors(['issue_resolved' => 'Non puoi completare un appuntamento la cui data non è ancora arrivata (' . $maintenanceRecord->appointment_date_formatted . ').']);
        }

        $issues = $maintenanceRecord->items->where('itemable_type', Issue::class);
        $deadlines = $maintenanceRecord->items->where('itemable_type', Deadline::class);

        // Transazione unica: aggiornamento intervento/guasto/scadenza deve essere atomico.
        DB::transaction(function () use ($maintenanceRecord, $data, $issues, $deadlines, $tireItems) {
            // 1) complete maintenance
            // Se l'utente ha già indicato una data di rientro (es. un tagliando
            // registrato retroattivamente), la rispettiamo. Altrimenti usiamo oggi.
            if (! $maintenanceRecord->return_date) {
                $maintenanceRecord->return_date = Carbon::today();
            }
            $maintenanceRecord->save();

            // 2) update issues
            foreach ($issues as $item) {
                $issue = $item->itemable;
                if ($issue) {
                    if ((bool) $data['issue_resolved']) {
                        $issue->status = 'closed';
                        $issue->save();
                    } else {
                        $issue->status = 'in_progress';
                        $issue->save();
                    }
                }
            }

            // 3) update deadlines + create next ones
            foreach ($deadlines as $item) {
                $deadline = $item->itemable;
                if (! $deadline || ! in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO], true)) {
                    continue;
                }

                if ((bool) $data['issue_resolved']) {
                    $this->renewDeadline($maintenanceRecord, $deadline);
                } else {
                    $deadline->status = 'pending';
                    $deadline->save();
                }
            }

            // 4) Cambio cinghia distribuzione: riparte la scadenza dalla data
            //    e dal chilometraggio del cambio effettuato.
            if ($maintenanceRecord->activity_type === MaintenanceRecord::ACTIVITY_TIMING_BELT && (bool) $data['issue_resolved']) {
                $this->renewTimingBeltDeadline($maintenanceRecord);
            }

            // 5) Cambio gomme: monta i set collegati (uno o più, es. anteriori
            // + posteriori insieme), applicando alle gomme sostituite la
            // disposizione scelta (magazzino o dismesse). Le chiamate in
            // sequenza si compongono correttamente anche quando un set
            // "full" già montato va diviso tra i due nuovi assi montati
            // (vedi TireChangeService).
            foreach ($tireItems as $tireItem) {
                if ($tireItem->completed) {
                    continue;
                }

                $this->tireChangeService->recordChange(
                    $tireItem->itemable,
                    $maintenanceRecord->return_date,
                    $maintenanceRecord->mileage_at_service,
                    $data['previous_disposition'],
                    "Appuntamento del {$maintenanceRecord->appointment_date_formatted}",
                );
                $tireItem->update(['completed' => true]);
            }
        });

        return redirect()
            ->route('admin.maintenance-records.show', $maintenanceRecord->id)
            ->with('status', 'Intervento completato con successo.');
    }

    /**
     * Per un appuntamento "Cambio Gomme", collega i set di gomme da
     * montare insieme: uno o più set esistenti (in magazzino) e/o uno
     * nuovo appena descritto (validati in ValidatesTireSelection: la
     * combinazione deve coprire esattamente 4 gomme della stessa
     * stagionalità). Il montaggio vero e proprio (con la scelta di cosa
     * fare delle gomme sostituite) avviene solo al completamento
     * dell'appuntamento (vedi complete()), non qui.
     */
    private function linkTireItems(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        if (($data['activity_type'] ?? null) !== MaintenanceRecord::ACTIVITY_TIRE_CHANGE) {
            return;
        }

        $tires = collect();

        if (! empty($data['target_tire_ids'])) {
            $tires = Tire::whereIn('id', $data['target_tire_ids'])
                ->where('vehicle_id', $data['vehicle_id'])
                ->get();
        }

        if (! empty($data['new_tire_season'])) {
            $tires->push(Tire::create([
                'vehicle_id' => $data['vehicle_id'],
                'season' => $data['new_tire_season'],
                'axle' => $data['new_tire_axle'] ?? Tire::AXLE_FULL,
                'quantity' => $data['new_tire_quantity'] ?? 4,
                'brand' => $data['new_tire_brand'] ?? null,
                'model_name' => $data['new_tire_model_name'] ?? null,
                'size' => $data['new_tire_size'] ?? null,
                'status' => Tire::STATUS_STORED,
            ]));
        }

        foreach ($tires as $tire) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $tire->id,
                'itemable_type' => Tire::class,
                'completed' => false,
            ]);
        }
    }

    /**
     * Rinnova una scadenza: la marca come rinnovata e crea la successiva.
     * La base temporale è la data di RIENTRO (return_date).
     */
    private function renewDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        $deadline->status = 'renewed';
        $deadline->is_renewed = true;
        // Il km rilevato all'appuntamento (se inserito) è il km del
        // veicolo al momento di QUESTA revisione: va sulla scadenza appena
        // rinnovata, non su quella successiva (che non è ancora avvenuta).
        // Prima veniva riportato solo per tagliando/cinghia, mai per
        // ministeriale/ossigeno.
        if ($maintenanceRecord->mileage_at_service !== null) {
            $deadline->last_mileage = $maintenanceRecord->mileage_at_service;
        }
        $deadline->save();

        // Il km rilevato all'appuntamento è una lettura reale del
        // contachilometri: la registriamo nello storico chilometraggi del
        // veicolo (vedi MileageLogService), non solo sulla scadenza. Una
        // sola chiamata qui copre tutti i tipi (ministeriale/ossigeno/
        // tagliando/cinghia), a prescindere da dove il km finisce salvato
        // più sotto.
        $this->mileageLogService->recordReading(
            $maintenanceRecord->vehicle,
            $maintenanceRecord->return_date ?? Carbon::today(),
            $maintenanceRecord->mileage_at_service,
        );

        // Il tagliando ha una logica dedicata: la scadenza temporale
        // parte dalla data di RIENTRO e la scadenza km dai km
        // inseriti + intervallo del tipo veicolo.
        if ($deadline->type === Deadline::TYPE_TAGLIANDO) {
            $this->renewTagliandoDeadline($maintenanceRecord, $deadline);
            return;
        }

        // La cinghia ha una logica dedicata (intervallo giorni + km).
        if ($deadline->type === Deadline::TYPE_CINGHIA) {
            $this->renewTimingBeltDeadline($maintenanceRecord, $deadline);
            return;
        }

        // Tutte le scadenze partono dalla data di RIENTRO.
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $nextDueDate = null;
        if ($deadline->type === Deadline::TYPE_MINISTERIAL && ($maintenanceRecord->vehicle->vehicleType?->regular_inspection_months ?? 0) > 0) {
            $monthsToAdd = (int) $maintenanceRecord->vehicle->vehicleType?->regular_inspection_months;
            $nextDueDate = $baseDate->copy()->addMonthsNoOverflow($monthsToAdd);
        } elseif ($deadline->type === Deadline::TYPE_OXYGEN && Deadline::supportsOxygenCheckForVehicle($maintenanceRecord->vehicle)) {
            $nextDueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
        }
        if ($nextDueDate) {
            // Stessa guardia anti-duplicati del rinnovo via form di
            // modifica (renews_deadline_id): prima questo controller aveva
            // una propria creazione senza quel collegamento, quindi la
            // guardia non poteva riconoscere una scadenza già creata da
            // qui, ed era possibile ottenerne un duplicato.
            $this->deadlineService->createNextOccurrence($deadline, $maintenanceRecord->vehicle, $nextDueDate);
        }
    }

    /**
     * Restituisce le descrizioni di tutti i guasti collegati, separate da virgola.
     * Se non ci sono guasti, restituisce una stringa vuota.
     */
    private function issueDescriptions(MaintenanceRecord $maintenanceRecord): string
    {
        return $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->map(fn($item) => $item->itemable?->description)
            ->filter()
            ->implode(', ');
    }

    /**
     * Tipologie delle scadenze collegate all'appuntamento (Tagliando,
     * Revisione Ministeriale, ecc.), una sola volta ciascuna.
     */
    private function deadlineTypes(MaintenanceRecord $maintenanceRecord): string
    {
        return $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->map(fn($item) => $item->itemable?->type)
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * Descrizione combinata di tutto ciò che l'appuntamento riguarda: guasti
     * e scadenze collegate insieme, non solo i guasti. Un appuntamento con
     * più elementi resta comunque una sola riga nell'elenco — qui si
     * costruisce solo il testo che ci va dentro.
     */
    private function itemDescriptions(MaintenanceRecord $maintenanceRecord): string
    {
        return collect([$this->issueDescriptions($maintenanceRecord), $this->deadlineTypes($maintenanceRecord)])
            ->filter(fn($part) => $part !== '')
            ->implode(' · ');
    }

    /**
     * Processa gli item marcati come completati in un appuntamento con data
     * di rientro: chiude i guasti e rinnova le scadenze.
     */
    private function processCompletedItems(MaintenanceRecord $maintenanceRecord, array $completedIssueIds, array $completedDeadlineIds): void
    {
        $maintenanceRecord->loadMissing(['items.itemable', 'vehicle.vehicleType']);

        // Chiudi i guasti completati
        if (! empty($completedIssueIds)) {
            Issue::whereIn('id', $completedIssueIds)->update(['status' => 'closed']);
        }

        // Rinnova le scadenze completate
        $completedDeadlines = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->whereIn('itemable_id', $completedDeadlineIds)
            ->map(fn($item) => $item->itemable)
            ->filter();

        foreach ($completedDeadlines as $deadline) {
            if (in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
                $this->renewDeadline($maintenanceRecord, $deadline);
            }
        }
    }

    /**
     * Rinnova la scadenza della cinghia di distribuzione dopo un cambio.
     * La nuova scadenza riparte dalla data e dal chilometraggio del cambio.
     * Crea SEMPRE una nuova scadenza per mantenere lo storico completo (una
     * per cambio effettuato), ma solo se questa non ne ha già una
     * successiva collegata (guardia in DeadlineService::createNextOccurrence).
     */
    private function renewTimingBeltDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $baseKm = $maintenanceRecord->mileage_at_service ?? 0;

        $this->deadlineService->createNextOccurrence(
            $deadline,
            $maintenanceRecord->vehicle,
            $baseDate->copy()->addDays(Deadline::TIMING_BELT_INTERVAL_DAYS),
            [
                'last_mileage' => $baseKm,
                'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
                'interval_days' => Deadline::TIMING_BELT_INTERVAL_DAYS,
            ]
        );
    }

    /**
     * Rinnova la scadenza del tagliando dopo il completamento.
     *
     * La scadenza temporale parte dalla data di RIENTRO del veicolo
     * (es. 18/10/2024 → 18/10/2025), mentre la scadenza km parte dai km
     * inseriti + l'intervallo del tipo veicolo (es. 16000 + 19000 = 35000).
     * Crea SEMPRE una nuova scadenza (una per tagliando effettuato), ma
     * solo se questa non ne ha già una successiva collegata.
     */
    private function renewTagliandoDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        // Base temporale: data di rientro
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $dueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);

        // Base km: km inseriti all'appuntamento + intervallo del tipo veicolo
        $baseKm = $maintenanceRecord->mileage_at_service;
        $intervalKm = (int) ($maintenanceRecord->vehicle->vehicleType?->regular_tagliando_km ?? 20000);

        $this->deadlineService->createNextOccurrence(
            $deadline,
            $maintenanceRecord->vehicle,
            $dueDate,
            [
                'last_mileage' => $baseKm,
                'interval_km' => $intervalKm,
                'interval_days' => Deadline::TAGLIANDO_INTERVAL_MONTHS * 30,
            ]
        );
    }

    /**
     * Mostra la vista calendario degli appuntamenti.
     */
    public function calendar()
    {
        return view('admin.maintenance-records.calendar');
    }

    /**
     * Endpoint JSON per FullCalendar.
     */
    public function events(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $records = MaintenanceRecord::with(['vehicle', 'provider'])
            ->whereBetween('appointment_date', [$request->start, $request->end])
            ->get();

        return response()->json(
            $records->map(function ($record) {
                $color = match ($record->activity_type) {
                    'Riparazione' => '#dc3545',
                    'Revisione Ministeriale' => '#0d6efd',
                    'Revisione Impianto Ossigeno' => '#6610f2',
                    'Tagliando' => '#198754',
                    'Cambio Gomme' => '#fd7e14',
                    'Lavaggio' => '#0dcaf0',
                    default => '#6c757d',
                };

                $title = $record->vehicle?->internal_code ?? 'N/A';
                if ($record->activity_type) {
                    $title .= ' - ' . $record->activity_type;
                }

                return [
                    'id' => $record->id,
                    'title' => $title,
                    'start' => $record->appointment_date?->toDateString(),
                    'end' => $record->return_date?->toDateString(),
                    'color' => $color,
                    'textColor' => '#fff',
                    'url' => route('admin.maintenance-records.show', $record->id),
                    'extendedProps' => [
                        'vehicle' => $record->vehicle?->internal_code,
                        'provider' => $record->provider?->name,
                        'activity_type' => $record->activity_type,
                    ],
                ];
            })
        );
    }
}
