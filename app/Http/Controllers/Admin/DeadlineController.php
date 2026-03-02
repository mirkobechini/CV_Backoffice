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
    public function index()
    {
        $deadlines = Deadline::with('vehicle')->get();
        $deadlines->each->syncStatusFromRules();
        return view('admin.deadlines.index', compact('deadlines'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
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
                'due_date' => 'nullable|date|required_unless:type,Revisione Ministeriale',
                'mark_as_renewed' => 'nullable|boolean',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
                'due_date.date' => 'La data di scadenza deve essere una data valida.',
                'mark_as_renewed.boolean' => 'Il valore di rinnovo non è valido.',
            ]
        );

        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);
        $dueDate = $this->resolveDueDate($data, $vehicle);

        if (!$dueDate) {
            return back()
                ->withErrors(['due_date' => 'Impossibile calcolare la data di revisione ministeriale: controlla immatricolazione e configurazione tipo veicolo.'])
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
        $vehicles = Vehicle::all();
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
                'due_date' => 'nullable|date|required_unless:type,Revisione Ministeriale',
                'mark_as_renewed' => 'nullable|boolean',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
                'due_date.date' => 'La data di scadenza deve essere una data valida.',
                'mark_as_renewed.boolean' => 'Il valore di rinnovo non è valido.',
            ]
        );

        $vehicle = Vehicle::with('vehicleType')->findOrFail($data['vehicle_id']);
        $dueDate = $this->resolveDueDate($data, $vehicle, $deadline->id);

        if (!$dueDate) {
            return back()
                ->withErrors(['due_date' => 'Impossibile calcolare la data di revisione ministeriale: controlla immatricolazione e configurazione tipo veicolo.'])
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

        return isset($data['due_date']) ? Carbon::parse($data['due_date']) : null;
    }

    private function resolveStatus(Carbon $dueDate, bool $markAsRenewed): string
    {
        if ($markAsRenewed) {
            return 'renewed';
        }

        return $dueDate->isBefore(Carbon::today()) ? 'expired' : 'pending';
    }
}
