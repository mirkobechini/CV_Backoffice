<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\DetectsDuplicates;
use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentIssueRequest;
use App\Http\Requests\UpdateEquipmentIssueRequest;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use Illuminate\Http\Request;

class EquipmentIssueController extends Controller
{
    use DetectsDuplicates;
    use SortableAndGroupable;

    public function __construct()
    {
        $this->authorizeResource(EquipmentIssue::class, 'equipmentIssue');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:equipment,status,date',
            'sort_by' => 'nullable|in:equipment,status,date',
            'sort_dir' => 'nullable|in:asc,desc',
            'status_filter' => 'nullable|in:all,open,in_progress,closed',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');
        $statusFilter = $validated['status_filter'] ?? 'all';
        $search = $request->get('q');

        // Stessa logica permissiva dell'elenco attrezzature: quelle non
        // assegnate a un veicolo (vehicle_id null) non hanno un gruppo
        // proprio e restano visibili a tutti; solo quelle assegnate
        // vengono filtrate sul gruppo del veicolo.
        $issuesQuery = EquipmentIssue::with('equipment.equipmentType', 'equipment.vehicle')
            ->whereHas('equipment', function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            });

        if ($search) {
            $issuesQuery->where(function ($sub) use ($search) {
                $sub->where('description', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('equipment', function ($eq) use ($search) {
                        $eq->where('name', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($statusFilter !== 'all') {
            $issuesQuery->where('status', $statusFilter);
        }

        $issues = $this->applySorting(
            $issuesQuery,
            $sortBy,
            $sortDir,
            [
                'equipment' => fn (EquipmentIssue $i) => $i->equipment->name ?: ($i->equipment->equipmentType->name ?? ''),
                'status' => 'status',
                'date' => 'event_date',
            ]
        );

        $groupedIssues = $this->applyGrouping($issues, $groupBy, function (EquipmentIssue $issue) use ($groupBy) {
            return match ($groupBy) {
                'equipment' => $issue->equipment->name ?: ($issue->equipment->equipmentType->name ?? 'N/A'),
                'status' => $issue->status_label,
                'date' => $issue->event_date
                    ? ucfirst($issue->event_date->locale('it')->translatedFormat('F Y'))
                    : 'N/A',
            };
        });

        return view('admin.equipment-issues.index', compact('issues', 'groupBy', 'sortBy', 'sortDir', 'groupedIssues', 'statusFilter') + [
            'groupToggleUrl' => fn ($f) => $this->groupToggleUrl($f, $groupBy, 'admin.equipment-issues.index'),
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.equipment-issues.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $equipments = Equipment::with('equipmentType', 'vehicle')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->orderBy('name')
            ->get();
        $selectedEquipmentId = request('equipment_id');

        return view('admin.equipment-issues.create', compact('equipments', 'selectedEquipmentId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentIssueRequest $request)
    {
        $data = $request->validated();

        $duplicateIssue = $this->findDuplicate(EquipmentIssue::class, [
            'equipment_id' => $data['equipment_id'],
            'description' => $data['description'],
            'event_date' => $data['event_date'],
            'status' => $data['status'],
        ]);

        if ($duplicateIssue) {
            return redirect()
                ->route('admin.equipment-issues.show', $duplicateIssue->id)
                ->with('status', 'Guasto già registrato: creazione duplicata bloccata.');
        }

        $newIssue = EquipmentIssue::create($data);

        return redirect()->route('admin.equipment-issues.show', $newIssue->id)->with('status', 'Guasto aggiunto con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EquipmentIssue $equipmentIssue)
    {
        $equipmentIssue->load('equipment.equipmentType', 'equipment.vehicle');

        return view('admin.equipment-issues.show', compact('equipmentIssue'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EquipmentIssue $equipmentIssue)
    {
        $equipments = Equipment::with('equipmentType', 'vehicle')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->orderBy('name')
            ->get();

        return view('admin.equipment-issues.edit', compact('equipmentIssue', 'equipments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentIssueRequest $request, EquipmentIssue $equipmentIssue)
    {
        $equipmentIssue->update($request->validated());

        return redirect()->route('admin.equipment-issues.show', $equipmentIssue->id)->with('status', 'Guasto aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EquipmentIssue $equipmentIssue)
    {
        $this->authorize('delete', $equipmentIssue);
        $equipmentIssue->delete();

        return redirect()->route('admin.equipment-issues.index')->with('status', 'Guasto eliminato con successo.');
    }
}
