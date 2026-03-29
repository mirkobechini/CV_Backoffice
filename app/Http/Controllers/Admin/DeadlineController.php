<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use Carbon\Carbon;
use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
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

        $deadlines = Deadline::with('vehicle')->get();
        $deadlines->each->syncStatusFromRules();

        if ($latestRevisionOnly) {
            $deadlines = $deadlines
                ->filter(fn(Deadline $deadline) => in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN], true))
                ->sortByDesc(fn(Deadline $deadline) => $deadline->due_date?->format('Y-m-d') ?? '')
                // Teniamo una sola scadenza per coppia (mezzo + tipo revisione): la più recente.
                ->unique(fn(Deadline $deadline) => ($deadline->vehicle_id ?? 'N/A') . '|' . ($deadline->type ?? 'N/A'))
                ->values();
        }

        $deadlines = $sortDir === 'desc'
            ? $deadlines->sortByDesc(function (Deadline $deadline) use ($sortBy) {
                return match ($sortBy) {
                    'type' => $deadline->type,
                    'status' => $deadline->automatic_status,
                    'vehicle' => $deadline->vehicle?->internal_code ?? '',
                    'date' => $deadline->due_date?->format('Y-m-d') ?? '',
                };
            })->values()
            : $deadlines->sortBy(function (Deadline $deadline) use ($sortBy) {
                return match ($sortBy) {
                    'type' => $deadline->type,
                    'status' => $deadline->automatic_status,
                    'vehicle' => $deadline->vehicle?->internal_code ?? '',
                    'date' => $deadline->due_date?->format('Y-m-d') ?? '',
                };
            })->values();

        $groupedDeadlines = null;
        if ($groupBy !== null) {
            $groupedDeadlines = $deadlines->groupBy(function (Deadline $deadline) use ($groupBy) {
                return match ($groupBy) {
                    'type' => $deadline->type ?? 'N/A',
                    'status' => match ($deadline->automatic_status) {
                        'renewed' => 'Rinnovata',
                        'pending' => 'In scadenza',
                        'expired' => 'Scaduta',
                        default => 'Sconosciuto',
                    },
                    'vehicle' => $deadline->vehicle?->internal_code ?? 'N/A',
                    'date' => $deadline->due_date_formatted ?? 'N/A',
                };
            });
        }

        return view('admin.deadlines.index', compact('deadlines', 'groupBy', 'sortBy', 'sortDir', 'groupedDeadlines', 'latestRevisionOnly'));
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

        $dueDate = $this->resolveDueDate($data, $vehicle);

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
            'status' => $this->resolveStatus($dueDate, $markAsRenewed),
            'is_renewed' => $markAsRenewed,
        ]);

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
    //TODO: lo status è sbagliato, se la scadenza è in scadenza o scaduta e spunto "mark as renewed" dovrebbe diventare "rinnovata" e creare quella successiva chiedendo la data di rinnovo(se non inserita viene calcolata in automatico), se invece è già lontana dovrebbe rimanere "rinnovata"
    public function update(UpdateDeadlineRequest $request, Deadline $deadline)
    {
        $data = $request->validated();

        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);

        if ($data['type'] === Deadline::TYPE_OXYGEN && !Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            return back()
                ->withErrors(['type' => 'La revisione impianto ossigeno è disponibile solo per le ambulanze.'])
                ->withInput();
        }

        $dueDate = $this->resolveDueDate($data, $vehicle, $deadline->id);

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
            'status' => $this->resolveStatus($dueDate, $markAsRenewed),
            'is_renewed' => $markAsRenewed,
        ]);

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

    private function resolveStatus(Carbon $dueDate, bool $markAsRenewed): string
    {
        $today = Carbon::today();
        $monthDiff = $today->diffInMonths($dueDate, false);    

        if ($markAsRenewed) {
            if ($monthDiff > 3) {
                return 'renewed';
            } else if ($monthDiff <= 3 && $dueDate->isAfter($today)) {
                return 'pending';
            } else {
                return 'expired';
            }
        }else {
            if ($dueDate->isAfter($today->addMonths(3))) {
                return 'renewed';
            } else if ($dueDate->isAfter($today)) {
                return 'pending';
            } else {
                return 'expired';
            }
        }
    }
}
