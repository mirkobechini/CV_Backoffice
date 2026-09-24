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
use Illuminate\Support\Carbon;

class DeadlineController extends Controller
{
    use SortableAndGroupable;

    /**
     * Tipologie valide per il filtro "tipo" dell'elenco, nello stesso ordine
     * mostrato nei form di creazione/modifica.
     */
    private const TYPES = [
        'Assicurazione',
        Deadline::TYPE_MINISTERIAL,
        Deadline::TYPE_OXYGEN,
        Deadline::TYPE_TAGLIANDO,
        Deadline::TYPE_CINGHIA,
    ];

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
            // Lista di chiavi separate da virgola (es. "vehicle,type"): permette
            // di combinare più raggruppamenti annidati invece di uno solo.
            'group_by' => 'nullable|string',
            'sort_by' => 'nullable|in:type,status,vehicle,date',
            'sort_dir' => 'nullable|in:asc,desc',
            'latest_revision_only' => 'nullable|in:0,1',
            'status_filter' => 'nullable|in:all,expired,pending,valid,renewed',
            'type_filter' => 'nullable|in:all,' . implode(',', self::TYPES),
        ]);

        // Default atteso alla prima visita (nessun filtro in query string):
        // raggruppato per tipo e limitato all'ultima revisione per veicolo.
        // "none"/"0" espliciti nella query string permettono di disattivarli.
        $rawGroupBy = $validated['group_by'] ?? ($request->has('group_by') ? null : 'type');
        $groupByKeys = [];
        if ($rawGroupBy && $rawGroupBy !== 'none') {
            $groupByKeys = array_values(array_intersect(
                ['type', 'status', 'vehicle', 'date'],
                array_unique(array_filter(explode(',', $rawGroupBy)))
            ));
        }
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');
        $latestRevisionOnly = $request->has('latest_revision_only') ? $validated['latest_revision_only'] === '1' : true;
        $statusFilter = $validated['status_filter'] ?? 'all';
        $typeFilter = $validated['type_filter'] ?? 'all';
        $search = $request->get('q');

        $deadlinesQuery = Deadline::with('vehicle.latestMileageLog')
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser());

        // Deadline::$searchable copre solo type/status: il campo cerca
        // "tipologia o veicolo" (vedi placeholder in vista), quindi il
        // veicolo va cercato esplicitamente sulla relazione, non tramite lo
        // scope search() generico che non risale mai al veicolo.
        if ($search) {
            $deadlinesQuery->where(function ($sub) use ($search) {
                $sub->where('type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('internal_code', 'like', "%{$search}%")
                            ->orWhere('license_plate', 'like', "%{$search}%");
                    });
            });
        }

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

        if ($typeFilter !== 'all') {
            $deadlines = $deadlines->filter(fn(Deadline $d) => $d->type === $typeFilter)->values();
        }

        $deadlines = $this->applySortingToCollection($deadlines, $sortBy, $sortDir, [
            'type' => fn(Deadline $d) => $d->type,
            'status' => fn(Deadline $d) => $d->automatic_status,
            'vehicle' => fn(Deadline $d) => $d->vehicle?->internal_code ?? '',
            'date' => fn(Deadline $d) => $d->due_date?->format('Y-m-d') ?? '',
        ]);

        // Ogni chiave di raggruppamento attiva ha il suo callback etichetta;
        // Collection::groupBy() accetta un array di callback e produce da
        // solo un raggruppamento annidato (veicolo > tipo, tipo > veicolo,
        // ecc. nell'ordine in cui le chiavi sono state attivate), invece di
        // poterne applicare uno solo alla volta.
        $groupLabelCallbacks = [
            'type' => fn(Deadline $deadline) => $deadline->type ?? 'N/A',
            'status' => fn(Deadline $deadline) => match ($deadline->automatic_status) {
                Deadline::STATUS_RENEWED => 'Rinnovata',
                Deadline::STATUS_PENDING => 'In scadenza',
                Deadline::STATUS_EXPIRED => 'Scaduta',
                Deadline::STATUS_VALID => 'Valida',
                default => 'Sconosciuto',
            },
            'vehicle' => fn(Deadline $deadline) => $deadline->vehicle?->internal_code ?? 'N/A',
            'date' => fn(Deadline $deadline) => $deadline->due_date_formatted ?? 'N/A',
        ];

        $groupedDeadlines = empty($groupByKeys)
            ? null
            : $deadlines->groupBy(array_map(fn($key) => $groupLabelCallbacks[$key], $groupByKeys));

        return view('admin.deadlines.index', compact('deadlines', 'groupByKeys', 'sortBy', 'sortDir', 'groupedDeadlines', 'latestRevisionOnly', 'statusFilter', 'typeFilter') + [
            'types' => self::TYPES,
            'groupToggleUrl' => fn($f) => $this->multiGroupToggleUrl($f, $groupByKeys, 'admin.deadlines.index'),
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
     * Rinnova una scadenza periodica senza un appuntamento in officina: per
     * un veicolo acquistato usato, la cui ultima revisione/tagliando/
     * cinghia è già stata effettuata dal precedente proprietario prima che
     * questo sistema iniziasse a tracciare il veicolo. Vedi
     * DeadlineService::renewWithoutAppointment().
     */
    public function renew(Request $request, Deadline $deadline)
    {
        $this->authorize('update', $deadline);

        if (! in_array($deadline->type, DeadlineService::RENEWABLE_TYPES, true)) {
            abort(400, 'Questa tipologia di scadenza non supporta il rinnovo automatico.');
        }

        $data = $request->validate([
            'renewed_date' => 'required|date_format:Y-m',
            'mileage' => 'nullable|integer|min:0',
        ], [
            'renewed_date.required' => 'La data di rinnovo è obbligatoria.',
            'renewed_date.date_format' => 'La data di rinnovo deve essere nel formato mese/anno valido.',
            'mileage.integer' => 'Il chilometraggio deve essere un numero intero.',
            'mileage.min' => 'Il chilometraggio non può essere negativo.',
        ]);

        $renewedDate = Carbon::createFromFormat('Y-m', $data['renewed_date'])->endOfMonth();
        $vehicle = $deadline->vehicle;

        $this->deadlineService->renewWithoutAppointment($deadline, $vehicle, $renewedDate, $data['mileage'] ?? null);

        return back()->with('success', 'Scadenza rinnovata con successo.');
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

        // Se questa scadenza aveva rinnovato automaticamente quella
        // precedente (creazione di una nuova Revisione Ministeriale/Ossigeno
        // per lo stesso veicolo), eliminandola la precedente torna ad essere
        // quella attuale: annulliamo il rinnovo e ricalcoliamo lo stato, che
        // risulterà "scaduta" dato che la sua data è già passata.
        $previousDeadline = $deadline->renewsDeadline;

        $deadline->delete();

        if ($previousDeadline) {
            $previousDeadline->update(['is_renewed' => false]);
            $previousDeadline->syncStatusFromRules();
        }

        $back = $request->input('back');
        if ($back && ! str_starts_with($back, $showUrl)) {
            return redirect($back)->with('success', 'Scadenza eliminata con successo.');
        }

        return redirect()->route('admin.deadlines.index')->with('success', 'Scadenza eliminata con successo.');
    }
}
