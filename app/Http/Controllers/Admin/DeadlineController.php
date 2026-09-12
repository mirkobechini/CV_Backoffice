<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Services\DeadlineService;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    use SortableAndGroupable;

    public function __construct(
        private readonly DeadlineService $deadlineService,
    ) {
        $this->authorizeResource(Deadline::class, 'deadline');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:type,status,vehicle,date,none',
            'sort_by' => 'nullable|in:type,status,vehicle,date',
            'sort_dir' => 'nullable|in:asc,desc',
            'latest_revision_only' => 'nullable|in:0,1',
            'status_filter' => 'nullable|in:all,expired,pending,valid,renewed',
        ]);

        // Default atteso alla prima visita (nessun filtro in query string):
        // raggruppato per tipo e limitato all'ultima revisione per veicolo.
        // "none"/"0" espliciti nella query string permettono di disattivarli.
        $groupBy = $validated['group_by'] ?? ($request->has('group_by') ? null : 'type');
        if ($groupBy === 'none') {
            $groupBy = null;
        }
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');
        $latestRevisionOnly = $request->has('latest_revision_only') ? $validated['latest_revision_only'] === '1' : true;
        $statusFilter = $validated['status_filter'] ?? 'all';

        $deadlinesQuery = Deadline::with('vehicle.latestMileageLog')->search($request->get('q'));

        // Se latestRevisionOnly, teniamo solo l'ultima scadenza per
        // veicolo+tipo: si applica a tutte le tipologie (non solo
        // ministeriale/ossigeno), dato che anche il tagliando si rinnova
        // creando un nuovo record e accumula storico allo stesso modo.
        // Prima filtrava solo su ministeriale/ossigeno, nascondendo del
        // tutto tagliando/cinghia/assicurazione dalla vista di default.
        if ($latestRevisionOnly) {
            $deadlines = $deadlinesQuery
                ->get()
                ->sortByDesc(fn(Deadline $d) => $d->due_date?->format('Y-m-d') ?? '')
                ->unique(fn(Deadline $d) => ($d->vehicle_id ?? 'N/A') . '|' . ($d->type ?? 'N/A'))
                ->values();
        } else {
            $deadlines = $deadlinesQuery->get();
        }

        Deadline::syncStatusesFromRules($deadlines);

        if ($statusFilter !== 'all') {
            $deadlines = $deadlines->filter(fn(Deadline $d) => $d->automatic_status === $statusFilter)->values();
        }

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

        return view('admin.deadlines.index', compact('deadlines', 'groupBy', 'sortBy', 'sortDir', 'groupedDeadlines', 'latestRevisionOnly', 'statusFilter') + [
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
        $vehicles = Vehicle::with('vehicleType')->forCurrentUser()->get();
        $selectedVehicleId = request('vehicle_id');

        return view('admin.deadlines.create', compact('vehicles', 'selectedVehicleId'));
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
        $vehicles = Vehicle::with('vehicleType')->forCurrentUser()->get();

        return view('admin.deadlines.edit', compact('deadline', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    // TODO: quando si spunta "mark as renewed" su una scadenza in scadenza/scaduta, creare automaticamente la scadenza successiva con data di rinnovo opzionale (se non inserita, calcolata in automatico).invece è già lontana dovrebbe rimanere "rinnovata"
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
    public function destroy(Request $request, Deadline $deadline)
    {
        $this->authorize('delete', $deadline);

        // Il modale di conferma imposta sempre "back" sull'URL della pagina
        // corrente: se l'eliminazione parte dalla show, "back" punta alla
        // scadenza appena eliminata e il redirect darebbe 404. In quel caso
        // torniamo all'indice invece di seguirlo.
        $showUrl = route('admin.deadlines.show', $deadline);

        $deadline->delete();

        $back = $request->input('back');
        if ($back && ! str_starts_with($back, $showUrl)) {
            return redirect($back)->with('success', 'Scadenza eliminata con successo.');
        }

        return redirect()->route('admin.deadlines.index')->with('success', 'Scadenza eliminata con successo.');
    }
}
