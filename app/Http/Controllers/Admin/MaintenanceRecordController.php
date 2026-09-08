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
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceRecordController extends Controller
{
    use DetectsDuplicates;
    use SortableAndGroupable;

    public function __construct()
    {
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
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');

        $maintenanceRecords = $this->applySorting(MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable']), $sortBy, $sortDir, [
            'vehicle' => fn(MaintenanceRecord $r) => $r->vehicle?->internal_code ?? '',
            'description' => fn(MaintenanceRecord $r) => $this->issueDescriptions($r) !== '' ? $this->issueDescriptions($r) : ($r->activity_type ?? ''),
            'date' => 'appointment_date',
        ]);

        $groupedMaintenanceRecords = $this->applyGrouping($maintenanceRecords, $groupBy, function (MaintenanceRecord $record) use ($groupBy) {
            return match ($groupBy) {
                'vehicle' => $record->vehicle?->internal_code ?? 'N/A',
                'description' => $this->issueDescriptions($record) !== '' ? $this->issueDescriptions($record) : ($record->activity_type ?? 'N/A'),
                'date' => $record->appointment_date
                    ? ucfirst($record->appointment_date->locale('it')->translatedFormat('F Y'))
                    : 'N/A',
            };
        });

        return view('admin.maintenance-records.index', compact('maintenanceRecords', 'groupBy', 'sortBy', 'sortDir', 'groupedMaintenanceRecords') + [
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

        // La view usa old(..., $preselected...) così old() ha priorità
        // dopo un errore validazione, altrimenti usa le preselezioni.
        return view('admin.maintenance-records.create', compact('vehicles', 'providers', 'openIssues', 'closedIssues', 'pendingDeadlines', 'preselectedIssueId', 'preselectedVehicleId'));
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
        ]);

        if (! empty($data['issue_ids'])) {
            foreach ($data['issue_ids'] as $issueId) {
                $newRecord->items()->create([
                    'itemable_id' => $issueId,
                    'itemable_type' => Issue::class,
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
                ]);
            }
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

        return view('admin.maintenance-records.edit', compact('maintenanceRecord', 'vehicles', 'providers', 'openIssues', 'closedIssues', 'pendingDeadlines'));
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
        ]);

        // Sincronizza gli item: cancella e ricrea
        // Prima di cancellare, registra i guasti attualmente collegati
        $oldIssueIds = $maintenanceRecord->items()
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id')
            ->toArray();

        $maintenanceRecord->items()->delete();

        $newIssueIds = [];
        if (! empty($data['issue_ids'])) {
            foreach ($data['issue_ids'] as $issueId) {
                $maintenanceRecord->items()->create([
                    'itemable_id' => $issueId,
                    'itemable_type' => Issue::class,
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
                ]);
            }
        }

        return redirect()->route('admin.maintenance-records.show', $maintenanceRecord->id)->with('status', 'Intervento aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaintenanceRecord $maintenanceRecord)
    {
        $this->authorize('delete', $maintenanceRecord);
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

        // Elimina gli item della pivot prima del soft-delete
        $maintenanceRecord->items()->delete();
        $maintenanceRecord->delete();

        return redirect()->route('admin.maintenance-records.index')->with('status', 'Intervento eliminato con successo.');
    }

    // --- CUSTOM METHOD ---
    // Metodo per completare un intervento e aggiornare lo stato del guasto associato
    public function complete(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $this->authorize('update', $maintenanceRecord);
        $data = $request->validate(
            [
                'issue_resolved' => 'required|boolean',
            ],
            [
                'issue_resolved.required' => 'Seleziona se il guasto è stato risolto o meno.',
                'issue_resolved.boolean' => 'Il valore selezionato non è valido.',
            ]
        );

        $maintenanceRecord->loadMissing(['items.itemable', 'vehicle.vehicleType']);

        $issues = $maintenanceRecord->items->where('itemable_type', Issue::class);
        $deadlines = $maintenanceRecord->items->where('itemable_type', Deadline::class);

        // Transazione unica: aggiornamento intervento/guasto/scadenza deve essere atomico.
        DB::transaction(function () use ($maintenanceRecord, $data, $issues, $deadlines) {
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
                    $deadline->status = 'renewed';
                    $deadline->is_renewed = true;
                    $deadline->save();

                    // Il tagliando ha una logica dedicata: la scadenza temporale
                    // parte dalla data di APPUNTAMENTO e la scadenza km dai km
                    // inseriti + intervallo del tipo veicolo.
                    if ($deadline->type === Deadline::TYPE_TAGLIANDO) {
                        $this->renewTagliandoDeadline($maintenanceRecord, $deadline);
                        continue;
                    }

                    $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
                    $nextDueDate = null;
                    if ($deadline->type === Deadline::TYPE_MINISTERIAL && ($maintenanceRecord->vehicle->vehicleType?->regular_inspection_months ?? 0) > 0) {
                        $monthsToAdd = (int) $maintenanceRecord->vehicle->vehicleType?->regular_inspection_months;
                        $nextDueDate = $baseDate->copy()->addMonthsNoOverflow($monthsToAdd);
                    } elseif ($deadline->type === Deadline::TYPE_OXYGEN && Deadline::supportsOxygenCheckForVehicle($maintenanceRecord->vehicle)) {
                        $nextDueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
                    }
                    if ($nextDueDate) {
                        Deadline::firstOrCreate(
                            [
                                'vehicle_id' => $maintenanceRecord->vehicle_id,
                                'type' => $deadline->type,
                                'due_date' => $nextDueDate->toDateString(),
                            ],
                            ['status' => 'pending']
                        );
                    }
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
        });

        return redirect()
            ->route('admin.maintenance-records.show', $maintenanceRecord->id)
            ->with('status', 'Intervento completato con successo.');
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
     * Rinnova la scadenza della cinghia di distribuzione dopo un cambio.
     * La nuova scadenza riparte dalla data e dal chilometraggio del cambio.
     */
    private function renewTimingBeltDeadline(MaintenanceRecord $maintenanceRecord): void
    {
        $deadline = $maintenanceRecord->vehicle->deadlines()
            ->where('type', Deadline::TYPE_CINGHIA)
            ->first();

        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $baseKm = $maintenanceRecord->mileage_at_service ?? 0;

        if ($deadline) {
            // Aggiorna la scadenza esistente
            $deadline->due_date = $baseDate->copy()->addDays(Deadline::TIMING_BELT_INTERVAL_DAYS);
            $deadline->last_mileage = $baseKm;
            $deadline->interval_km = Deadline::TIMING_BELT_INTERVAL_KM;
            $deadline->interval_days = Deadline::TIMING_BELT_INTERVAL_DAYS;
            $deadline->status = Deadline::STATUS_PENDING;
            $deadline->is_renewed = false;
            $deadline->save();
        } else {
            // Crea la scadenza se non esiste
            Deadline::create([
                'vehicle_id' => $maintenanceRecord->vehicle_id,
                'type' => Deadline::TYPE_CINGHIA,
                'due_date' => $baseDate->copy()->addDays(Deadline::TIMING_BELT_INTERVAL_DAYS)->toDateString(),
                'last_mileage' => $baseKm,
                'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
                'interval_days' => Deadline::TIMING_BELT_INTERVAL_DAYS,
                'status' => Deadline::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Rinnova la scadenza del tagliando dopo il completamento.
     *
     * La scadenza temporale parte dalla data di APPUNTAMENTO del tagliando
     * (es. 18/10/2024 → 18/10/2025), mentre la scadenza km parte dai km
     * inseriti + l'intervallo del tipo veicolo (es. 16000 + 19000 = 35000).
     */
    private function renewTagliandoDeadline(MaintenanceRecord $maintenanceRecord, Deadline $renewedDeadline): void
    {
        // Base temporale: data di appuntamento (non di rientro)
        $baseDate = Carbon::parse($maintenanceRecord->appointment_date ?? Carbon::today());
        $dueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);

        // Base km: km inseriti all'appuntamento + intervallo del tipo veicolo
        $baseKm = $maintenanceRecord->mileage_at_service;
        $intervalKm = (int) ($maintenanceRecord->vehicle->vehicleType?->regular_tagliando_km ?? 20000);

        $deadline = $maintenanceRecord->vehicle->deadlines()
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->where('id', '!=', $renewedDeadline->id)
            ->first();

        if ($deadline) {
            // Aggiorna la scadenza esistente
            $deadline->due_date = $dueDate->toDateString();
            $deadline->last_mileage = $baseKm;
            $deadline->interval_km = $intervalKm;
            $deadline->interval_days = Deadline::TAGLIANDO_INTERVAL_MONTHS * 30;
            $deadline->status = Deadline::STATUS_PENDING;
            $deadline->is_renewed = false;
            $deadline->save();
        } else {
            // Crea la nuova scadenza
            Deadline::create([
                'vehicle_id' => $maintenanceRecord->vehicle_id,
                'type' => Deadline::TYPE_TAGLIANDO,
                'due_date' => $dueDate->toDateString(),
                'last_mileage' => $baseKm,
                'interval_km' => $intervalKm,
                'interval_days' => Deadline::TAGLIANDO_INTERVAL_MONTHS * 30,
                'status' => Deadline::STATUS_PENDING,
            ]);
        }
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
