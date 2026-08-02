<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    use SortableAndGroupable;

    public function __construct(
        private readonly \App\Services\DeadlineService $deadlineService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:type,status,vehicle,date',
            'sort_by' => 'nullable|in:type,status,vehicle,date',
            'sort_dir' => 'nullable|in:asc,desc',
            'latest_revision_only' => 'nullable|in:0,1',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');
        $latestRevisionOnly = ($validated['latest_revision_only'] ?? '0') === '1';

        $deadlinesQuery = Deadline::with('vehicle.latestMileageLog')->search($request->get('q'));

        // Se latestRevisionOnly, carichiamo solo le ultime revisioni per veicolo
        // filtrando a monte per tipo, così il DB fa il lavoro pesante.
        if ($latestRevisionOnly) {
            $deadlines = $deadlinesQuery
                ->whereIn('type', [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN])
                ->get()
                ->sortByDesc(fn(Deadline $d) => $d->due_date?->format('Y-m-d') ?? '')
                ->unique(fn(Deadline $d) => ($d->vehicle_id ?? 'N/A') . '|' . ($d->type ?? 'N/A'))
                ->values();
        } else {
            $deadlines = $deadlinesQuery->get();
        }

        $deadlines->each->syncStatusFromRules();

        $deadlines = $this->applySortingToCollection($deadlines, $sortBy, $sortDir, [
            'type' => fn(Deadline $d) => $d->type,
            'status' => fn(Deadline $d) => $d->automatic_status,
            'vehicle' => fn(Deadline $d) => $d->vehicle?->internal_code ?? '',
            'date' => fn(Deadline $d) => $d->due_date?->format('Y-m-d') ?? '',
        ]);



        $groupedDeadlines = $this->applyGrouping($deadlines, $groupBy, function (Deadline $deadline) use ($groupBy) {
            return match ($groupBy) {
                'type' => $deadline->type ?? 'N/A',
                'status' => match ($deadline->automatic_status) {
                    Deadline::STATUS_RENEWED => 'Rinnovata',
                    Deadline::STATUS_PENDING => 'In scadenza',
                    Deadline::STATUS_EXPIRED => 'Scaduta',
                    Deadline::STATUS_VALID => 'Valida',
                    default => 'Sconosciuto',
                },
                'vehicle' => $deadline->vehicle?->internal_code ?? 'N/A',
                'date' => $deadline->due_date_formatted ?? 'N/A',
            };
        });

        return view('admin.deadlines.index', compact('deadlines', 'groupBy', 'sortBy', 'sortDir', 'groupedDeadlines', 'latestRevisionOnly') + [
            'groupToggleUrl' => fn($f) => $this->groupToggleUrl($f, $groupBy, 'admin.deadlines.index'),
            'sortToggleUrl' => fn($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.deadlines.index'),
            'sortIcon' => fn($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::with('vehicleType')->get();
        return view('admin.deadlines.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeadlineRequest $request)
    {
        $data = $request->validated();

        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);

        try {
            $deadline = $this->deadlineService->createDeadline($data, $vehicle);
            return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza creata con successo.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['due_date' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Deadline $deadline)
    {
        $deadline->syncStatusFromRules();
        return view('admin.deadlines.show', compact('deadline'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Deadline $deadline)
    {
        $vehicles = Vehicle::with('vehicleType')->get();
        return view('admin.deadlines.edit', compact('deadline', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    //TODO: quando si spunta "mark as renewed" su una scadenza in scadenza/scaduta, creare automaticamente la scadenza successiva con data di rinnovo opzionale (se non inserita, calcolata in automatico).invece è già lontana dovrebbe rimanere "rinnovata"
    public function update(UpdateDeadlineRequest $request, Deadline $deadline)
    {
        $data = $request->validated();

        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);

        try {
            $this->deadlineService->updateDeadline($deadline, $data, $vehicle);
            return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza aggiornata con successo.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['due_date' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deadline $deadline)
    {
        $deadline->delete();
        return redirect()->route('admin.deadlines.index')->with('success', 'Scadenza eliminata con successo.');
    }
}
