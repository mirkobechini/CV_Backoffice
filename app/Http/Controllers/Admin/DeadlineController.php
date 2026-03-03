<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'due_date' => 'nullable|date_format:Y-m|required_unless:type,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'mark_as_renewed' => 'nullable|boolean',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
                'due_date.date_format' => 'La data di scadenza deve essere nel formato mese/anno valido.',
                'mark_as_renewed.boolean' => 'Il valore di rinnovo non è valido.',
            ]
        );

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

        $markAsRenewed = (bool) ($data['mark_as_renewed'] ?? false);

        $deadline = new Deadline();
        $deadline->vehicle_id = $vehicle->id;
        $deadline->type = $data['type'];
        $deadline->due_date = $dueDate->toDateString();
        $deadline->status = $this->resolveStatus($dueDate, $markAsRenewed);
        $deadline->save();

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
    public function update(Request $request, Deadline $deadline)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'due_date' => 'nullable|date_format:Y-m|required_unless:type,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'mark_as_renewed' => 'nullable|boolean',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
                'due_date.date_format' => 'La data di scadenza deve essere nel formato mese/anno valido.',
                'mark_as_renewed.boolean' => 'Il valore di rinnovo non è valido.',
            ]
        );

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

        $markAsRenewed = (bool) ($data['mark_as_renewed'] ?? false);

        $deadline->vehicle_id = $vehicle->id;
        $deadline->type = $data['type'];
        $deadline->due_date = $dueDate->toDateString();
        $deadline->status = $this->resolveStatus($dueDate, $markAsRenewed);
        $deadline->update();

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
        if ($markAsRenewed) {
            return 'renewed';
        }

        return $dueDate->isBefore(Carbon::today()) ? 'expired' : 'pending';
    }
}
