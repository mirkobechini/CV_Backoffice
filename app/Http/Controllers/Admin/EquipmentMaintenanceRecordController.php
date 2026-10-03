<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentMaintenanceRecordRequest;
use App\Http\Requests\UpdateEquipmentMaintenanceRecordRequest;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EquipmentMaintenanceRecordController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(EquipmentMaintenanceRecord::class, 'equipmentMaintenanceRecord');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status_filter' => 'nullable|in:all,scheduled,completed',
        ]);
        $statusFilter = $validated['status_filter'] ?? 'all';
        $search = $request->get('q');

        // Visibile se almeno un'attrezzatura coinvolta è visibile all'utente
        // (stessa logica permissiva dell'elenco attrezzature: quelle non
        // assegnate a un veicolo restano visibili a tutti).
        $query = EquipmentMaintenanceRecord::with('provider', 'equipments.equipmentType')
            ->whereHas('equipments', function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            });

        if ($search) {
            $query->where(function ($sub) use ($search) {
                $sub->orWhereHas('provider', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('equipments', fn ($eq) => $eq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($statusFilter === 'scheduled') {
            $query->whereNull('return_date');
        } elseif ($statusFilter === 'completed') {
            $query->whereNotNull('return_date');
        }

        $allRecords = $query->orderByDesc('appointment_date')->get();

        $perPage = 20;
        $page = (int) $request->get('page', 1);
        $records = new LengthAwarePaginator(
            $allRecords->forPage($page, $perPage)->values(),
            $allRecords->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.equipment-maintenance-records.index', compact('records', 'statusFilter'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $equipments = $this->selectableEquipments();
        $selectedEquipmentId = request('equipment_id');
        $providers = Provider::all();
        $openIssues = EquipmentIssue::open()
            ->whereHas('equipment', fn ($q) => $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser()))
            ->get(['id', 'equipment_id', 'description', 'event_date']);

        return view('admin.equipment-maintenance-records.create', compact('equipments', 'selectedEquipmentId', 'providers', 'openIssues'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentMaintenanceRecordRequest $request)
    {
        $data = $request->validated();
        $completedIssueIds = $data['completed_issue_ids'] ?? [];

        $record = DB::transaction(function () use ($data, $completedIssueIds) {
            $record = EquipmentMaintenanceRecord::create([
                'provider_id' => $data['provider_id'],
                'appointment_date' => $data['appointment_date'],
                'return_date' => $data['return_date'] ?? null,
                'activity_type' => $data['activity_type'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $record->equipments()->sync($data['equipment_ids']);

            $this->linkIssues($record, $data['issue_ids'] ?? []);

            if ($record->return_date) {
                $this->closeCompletedIssues($completedIssueIds);
            }

            return $record;
        });

        return redirect()->route('admin.equipment-maintenance-records.show', $record->id)->with('status', 'Appuntamento aggiunto con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EquipmentMaintenanceRecord $equipmentMaintenanceRecord)
    {
        $equipmentMaintenanceRecord->load('provider', 'equipments.equipmentType', 'issues.equipment');

        return view('admin.equipment-maintenance-records.show', compact('equipmentMaintenanceRecord'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EquipmentMaintenanceRecord $equipmentMaintenanceRecord)
    {
        $equipmentMaintenanceRecord->load('equipments', 'issues');

        $equipments = $this->selectableEquipments();
        $providers = Provider::all();
        $linkedEquipmentIds = $equipmentMaintenanceRecord->equipments->pluck('id');
        $linkedIssueIds = $equipmentMaintenanceRecord->issues->pluck('id');

        // Selezionabili: i guasti aperti delle attrezzature collegate, più
        // quelli già collegati a questo appuntamento (anche se nel
        // frattempo risolti altrove), per poterli mantenere in modifica.
        $selectableIssues = EquipmentIssue::where(function ($q) use ($linkedEquipmentIds, $equipmentMaintenanceRecord) {
            $q->whereIn('equipment_id', $linkedEquipmentIds)->open();
        })->orWhere('equipment_maintenance_record_id', $equipmentMaintenanceRecord->id)
            ->get(['id', 'equipment_id', 'description', 'event_date']);

        return view('admin.equipment-maintenance-records.edit', compact(
            'equipmentMaintenanceRecord',
            'equipments',
            'providers',
            'linkedEquipmentIds',
            'linkedIssueIds',
            'selectableIssues'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentMaintenanceRecordRequest $request, EquipmentMaintenanceRecord $equipmentMaintenanceRecord)
    {
        $data = $request->validated();
        $completedIssueIds = $data['completed_issue_ids'] ?? [];

        DB::transaction(function () use ($data, $equipmentMaintenanceRecord, $completedIssueIds) {
            $equipmentMaintenanceRecord->update([
                'provider_id' => $data['provider_id'],
                'appointment_date' => $data['appointment_date'],
                'return_date' => $data['return_date'] ?? null,
                'activity_type' => $data['activity_type'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $equipmentMaintenanceRecord->equipments()->sync($data['equipment_ids']);

            // I guasti rimossi dalla selezione tornano scollegati (e in
            // lavorazione->aperto se non risolti altrove): loop su modelli
            // singoli, non query di massa, per non bypassare l'activity log
            // (stesso motivo già corretto per i guasti veicolo).
            $oldIssueIds = EquipmentIssue::where('equipment_maintenance_record_id', $equipmentMaintenanceRecord->id)->pluck('id')->all();
            $newIssueIds = $data['issue_ids'] ?? [];

            $removedIssueIds = array_diff($oldIssueIds, $newIssueIds);
            if (! empty($removedIssueIds)) {
                EquipmentIssue::whereIn('id', $removedIssueIds)->get()->each(function (EquipmentIssue $issue) {
                    if ($issue->status === 'in_progress') {
                        $issue->status = 'open';
                    }
                    $issue->equipment_maintenance_record_id = null;
                    $issue->save();
                });
            }

            $this->linkIssues($equipmentMaintenanceRecord, array_diff($newIssueIds, $oldIssueIds));

            if ($equipmentMaintenanceRecord->return_date) {
                $this->closeCompletedIssues($completedIssueIds);
            }
        });

        return redirect()->route('admin.equipment-maintenance-records.show', $equipmentMaintenanceRecord->id)->with('status', 'Appuntamento aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, EquipmentMaintenanceRecord $equipmentMaintenanceRecord)
    {
        $this->authorize('delete', $equipmentMaintenanceRecord);

        $equipmentMaintenanceRecord->loadMissing('issues');

        // I guasti in lavorazione tornano aperti (loop su modelli singoli,
        // non query di massa — vedi commento in update() sullo stesso tema).
        $equipmentMaintenanceRecord->issues->each(function (EquipmentIssue $issue) {
            if ($issue->status === 'in_progress') {
                $issue->status = 'open';
            }
            $issue->equipment_maintenance_record_id = null;
            $issue->save();
        });

        $equipmentMaintenanceRecord->equipments()->detach();
        $equipmentMaintenanceRecord->delete();

        $back = $request->input('back');
        if ($back) {
            return redirect($back)->with('status', 'Appuntamento eliminato con successo.');
        }

        return redirect()->route('admin.equipment-maintenance-records.index')->with('status', 'Appuntamento eliminato con successo.');
    }

    /**
     * Completa l'appuntamento: imposta la data di restituzione (oggi, se non
     * già indicata) e, se ci sono guasti collegati, li chiude o li
     * riporta in lavorazione a seconda della risposta.
     */
    public function complete(Request $request, EquipmentMaintenanceRecord $equipmentMaintenanceRecord)
    {
        $this->authorize('update', $equipmentMaintenanceRecord);

        $equipmentMaintenanceRecord->loadMissing('issues');
        $hasIssues = $equipmentMaintenanceRecord->issues->isNotEmpty();

        $rules = [];
        $messages = [];
        if ($hasIssues) {
            $rules['issue_resolved'] = 'required|boolean';
            $messages['issue_resolved.required'] = 'Seleziona se i guasti collegati sono stati risolti o meno.';
        }

        $data = $request->validate($rules, $messages);

        DB::transaction(function () use ($equipmentMaintenanceRecord, $data, $hasIssues) {
            if (! $equipmentMaintenanceRecord->return_date) {
                $equipmentMaintenanceRecord->return_date = Carbon::today();
            }
            $equipmentMaintenanceRecord->save();

            if ($hasIssues) {
                $resolved = (bool) $data['issue_resolved'];
                $equipmentMaintenanceRecord->issues->each(function (EquipmentIssue $issue) use ($resolved) {
                    $issue->status = $resolved ? 'closed' : 'in_progress';
                    $issue->save();
                });
            }
        });

        return redirect()
            ->route('admin.equipment-maintenance-records.show', $equipmentMaintenanceRecord->id)
            ->with('status', 'Appuntamento completato con successo.');
    }

    /**
     * Attrezzature selezionabili per un appuntamento: stessa logica
     * permissiva dell'elenco attrezzature (quelle non assegnate a un
     * veicolo restano visibili a tutti).
     */
    private function selectableEquipments()
    {
        return Equipment::with('equipmentType', 'vehicle')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Collega i guasti indicati a questo appuntamento: li marca come "in
     * lavorazione" se erano aperti (stesso schema usato per i guasti veicolo).
     */
    private function linkIssues(EquipmentMaintenanceRecord $record, array $issueIds): void
    {
        if (empty($issueIds)) {
            return;
        }

        EquipmentIssue::whereIn('id', $issueIds)->get()->each(function (EquipmentIssue $issue) use ($record) {
            $issue->equipment_maintenance_record_id = $record->id;
            if ($issue->status === 'open') {
                $issue->status = 'in_progress';
            }
            $issue->save();
        });
    }

    /**
     * Chiude i guasti marcati come completati quando l'appuntamento viene
     * creato/modificato già con una data di restituzione compilata.
     */
    private function closeCompletedIssues(array $completedIssueIds): void
    {
        if (empty($completedIssueIds)) {
            return;
        }

        EquipmentIssue::whereIn('id', $completedIssueIds)->get()->each(function (EquipmentIssue $issue) {
            $issue->status = 'closed';
            $issue->save();
        });
    }
}
