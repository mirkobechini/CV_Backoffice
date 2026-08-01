<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use Carbon\Carbon;
use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    use SortableAndGroupable;
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

        $deadlines = Deadline::with('vehicle')->search($request->get('q'))->get();
        $deadlines->each->syncStatusFromRules();

        if ($latestRevisionOnly) {
            $deadlines = $deadlines
                ->filter(fn(Deadline $deadline) => in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN], true))
                ->sortByDesc(fn(Deadline $deadline) => $deadline->due_date?->format('Y-m-d') ?? '')
                // Teniamo una sola scadenza per coppia (mezzo + tipo revisione): la più recente.
                ->unique(fn(Deadline $deadline) => ($deadline->vehicle_id ?? 'N/A') . '|' . ($deadline->type ?? 'N/A'))
                ->values();
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

        if ($data['type'] === Deadline::TYPE_OXYGEN && !Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            return back()
                ->withErrors(['type' => 'La revisione impianto ossigeno è disponibile solo per le ambulanze.'])
                ->withInput();
        }

        if (in_array($data['type'], [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
            // Tagliando e Cinghia: data fornita dall'utente, usiamo i campi km
            $dueDate = $data['due_date'] ? Carbon::createFromFormat('Y-m', $data['due_date'])?->endOfMonth() : null;
        } else {
            $dueDate = $this->resolveDueDate($data, $vehicle);
        }

        if (!$dueDate) {
            return back()
                ->withErrors(['due_date' => 'Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.'])
                ->withInput();
        }

        $markAsRenewed = (bool) ($data['is_renewed'] ?? false);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => $markAsRenewed,
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
        ]);
        $deadline->syncStatusFromRules();

        return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza creata con successo.');
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

        if ($data['type'] === Deadline::TYPE_OXYGEN && !Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            return back()
                ->withErrors(['type' => 'La revisione impianto ossigeno è disponibile solo per le ambulanze.'])
                ->withInput();
        }

        if (in_array($data['type'], [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
            // Tagliando e Cinghia: data fornita dall'utente, usiamo i campi km
            $dueDate = $data['due_date'] ? Carbon::createFromFormat('Y-m', $data['due_date'])?->endOfMonth() : null;
        } else {
            $dueDate = $this->resolveDueDate($data, $vehicle, $deadline->id);
        }

        if (!$dueDate) {
            return back()
                ->withErrors(['due_date' => 'Impossibile calcolare automaticamente la data di scadenza: controlla immatricolazione e configurazione tipo veicolo.'])
                ->withInput();
        }

        $markAsRenewed = (bool) ($data['is_renewed'] ?? false);

        $deadline->update([
            'vehicle_id' => $vehicle->id,
            'type' => $data['type'],
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => $markAsRenewed,
            'interval_km' => $data['interval_km'] ?? null,
            'last_mileage' => $data['last_mileage'] ?? null,
            'interval_days' => $data['interval_days'] ?? null,
        ]);
        $deadline->syncStatusFromRules();

        return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deadline $deadline)
    {
        $deadline->delete();
        return redirect()->route('admin.deadlines.index')->with('success', 'Scadenza eliminata con successo.');
    }

    private function resolveDueDate(array $data, Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        // Le revisioni (ministeriale/ossigeno) vengono calcolate automaticamente,
        // mentre per gli altri tipi la data arriva dal campo manuale YYYY-MM.
        if ($data['type'] === Deadline::TYPE_MINISTERIAL) {
            return Deadline::calculateMinisterialDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        if ($data['type'] === Deadline::TYPE_OXYGEN) {
            return Deadline::calculateOxygenDueDateForVehicle($vehicle, $excludeDeadlineId);
        }

        return $this->resolveManualDueDate($data['due_date'] ?? null);
    }

    private function resolveManualDueDate(?string $dueDate): ?Carbon
    {
        if (!$dueDate) {
            return null;
        }

        $parsedDate = Carbon::createFromFormat('Y-m', $dueDate);

        if (!$parsedDate) {
            return null;
        }

        return $parsedDate->endOfMonth();
    }
}
