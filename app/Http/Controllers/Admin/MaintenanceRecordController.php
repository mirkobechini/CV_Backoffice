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
use App\Models\Vehicle;
use App\Services\MaintenanceCompletionService;
use App\Services\MaintenanceRecordFormDataService;
use App\Services\MaintenanceRecordItemSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceRecordController extends Controller
{
    use DetectsDuplicates;
    use SortableAndGroupable;

    public function __construct(
        private readonly MaintenanceCompletionService $completionService,
        private readonly MaintenanceRecordFormDataService $formDataService,
        private readonly MaintenanceRecordItemSyncService $itemSyncService,
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
            'description' => fn(MaintenanceRecord $r) => $r->item_descriptions !== '' ? $r->item_descriptions : ($r->activity_type ?? ''),
            'date' => 'appointment_date',
        ]);

        $groupedMaintenanceRecords = $this->applyGrouping($maintenanceRecords, $groupBy, function (MaintenanceRecord $record) use ($groupBy) {
            return match ($groupBy) {
                'vehicle' => $record->vehicle?->internal_code ?? 'N/A',
                'description' => $record->item_descriptions !== '' ? $record->item_descriptions : ($record->activity_type ?? 'N/A'),
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

        // La view usa old(..., $preselected...) così old() ha priorità
        // dopo un errore validazione, altrimenti usa le preselezioni.
        return view(
            'admin.maintenance-records.create',
            $this->formDataService->forCreate() + compact('preselectedIssueId', 'preselectedVehicleId', 'preselectedActivityType')
        );
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

        // Transazione unica: creazione del record e collegamento di
        // guasti/scadenze/gomme (+ eventuale completamento immediato) devono
        // riuscire o fallire insieme — senza, un errore a metà lasciava il
        // record creato ma con item/stati guasto incoerenti.
        $newRecord = DB::transaction(function () use ($data) {
            $newRecord = MaintenanceRecord::create([
                'vehicle_id' => $data['vehicle_id'],
                'provider_id' => $data['provider_id'],
                'appointment_date' => $data['appointment_date'],
                'return_date' => $data['return_date'] ?? null,
                'activity_type' => $data['activity_type'] ?? null,
                'mileage_at_service' => $data['mileage_at_service'] ?? null,
                'cost' => $data['cost'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->itemSyncService->createForNewRecord($newRecord, $data);

            return $newRecord;
        });

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

        return view(
            'admin.maintenance-records.edit',
            ['maintenanceRecord' => $maintenanceRecord] + $this->formDataService->forEdit($maintenanceRecord)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaintenanceRecordRequest $request, MaintenanceRecord $maintenanceRecord)
    {
        $data = $request->validated();

        // Transazione unica, stesso motivo di store(): sincronizzare gli
        // item e l'eventuale completamento devono riuscire o fallire insieme.
        DB::transaction(function () use ($data, $maintenanceRecord) {
            $maintenanceRecord->update([
                'vehicle_id' => $data['vehicle_id'],
                'provider_id' => $data['provider_id'],
                'appointment_date' => $data['appointment_date'],
                'return_date' => $data['return_date'] ?? null,
                'activity_type' => $data['activity_type'] ?? null,
                'mileage_at_service' => $data['mileage_at_service'] ?? null,
                'cost' => $data['cost'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->itemSyncService->syncForExistingRecord($maintenanceRecord, $data);
        });

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

        $restoredDeadlines = $this->itemSyncService->restoreOnDelete($maintenanceRecord);

        // Elimina gli item della pivot prima del soft-delete
        $maintenanceRecord->items()->delete();
        $maintenanceRecord->delete();

        $message = 'Intervento eliminato con successo.';
        if (! empty($restoredDeadlines)) {
            $message .= ' Ripristinate le scadenze: ' . implode(', ', $restoredDeadlines) . '.';
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

        $tireItemsNeedingDisposition = $this->completionService->tireItemsRequiringDisposition($maintenanceRecord);

        $rules = [
            'issue_resolved' => 'required|boolean',
        ];
        $messages = [
            'issue_resolved.required' => 'Seleziona se il guasto è stato risolto o meno.',
            'issue_resolved.boolean' => 'Il valore selezionato non è valido.',
        ];

        if ($tireItemsNeedingDisposition->isNotEmpty()) {
            // Una scelta indipendente per ogni gomma sostituita (es. 1
            // dismessa e 3 in magazzino nello stesso cambio), non più
            // un'unica scelta per tutte: chiave = id della gomma montata.
            $rules['previous_disposition'] = 'required|array';
            $messages['previous_disposition.required'] = 'Indica cosa fare delle gomme sostituite.';
            foreach ($tireItemsNeedingDisposition as $item) {
                $rules["previous_disposition.{$item->itemable_id}"] = 'required|in:stored,retired';
            }
            $messages['previous_disposition.*.required'] = 'Indica cosa fare di ogni gomma sostituita.';
            $messages['previous_disposition.*.in'] = 'La scelta per le gomme sostituite non è valida.';
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

        $this->completionService->complete($maintenanceRecord, $data);

        return redirect()
            ->route('admin.maintenance-records.show', $maintenanceRecord->id)
            ->with('status', 'Intervento completato con successo.');
    }
}
