<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFuelLogRequest;
use App\Http\Requests\UpdateFuelLogRequest;
use App\Models\FuelCard;
use App\Models\FuelLog;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class FuelLogController extends Controller
{
    use SortableAndGroupable;

    public function __construct()
    {
        $this->authorizeResource(FuelLog::class, 'fuelLog');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:vehicle,date,liters',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $fuelLogs = $this->applySorting(
            FuelLog::with('vehicle')->whereHas('vehicle', fn ($q) => $q->forCurrentUser()),
            $sortBy,
            $sortDir,
            [
                'vehicle' => fn (FuelLog $l) => $l->vehicle?->internal_code ?? '',
                'date' => 'log_date',
                'liters' => 'liters',
            ]
        );

        // Consumo (km/l) rispetto al pieno precedente dello stesso veicolo,
        // stesso pattern già usato per il delta km nei chilometraggi: calcolato
        // su tutti i log del gruppo per non dipendere da ordinamento/paginazione
        // della tabella corrente.
        $consumptionByLogId = [];
        // orderBy('id') come tiebreaker (audit 2026-10-22, B7): con due
        // rifornimenti nella stessa data, l'ordine tra loro non era
        // deterministico (nessuna chiave secondaria), quindi "precedente"
        // poteva risultare quello sbagliato, calcolando un consumo errato
        // (o scartato per km negativi).
        $allLogsForConsumption = FuelLog::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->orderBy('log_date')
            ->orderBy('id')
            ->get(['id', 'vehicle_id', 'log_date', 'liters', 'mileage']);
        foreach ($allLogsForConsumption->groupBy('vehicle_id') as $logsForVehicle) {
            $previous = null;
            foreach ($logsForVehicle as $log) {
                $consumptionByLogId[$log->id] = FuelLog::consumptionKmPerLiter($log, $previous);
                $previous = $log;
            }
        }

        return view('admin.fuel-logs.index', compact('fuelLogs', 'sortBy', 'sortDir', 'consumptionByLogId') + [
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.fuel-logs.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::forCurrentUser()->get();
        $fuelCards = FuelCard::where('group_id', request()->user()->activeGroup()?->id)->get();

        return view('admin.fuel-logs.create', compact('vehicles', 'fuelCards'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFuelLogRequest $request)
    {
        $fuelLog = FuelLog::create($request->validated());

        return redirect()->route('admin.fuel-logs.show', $fuelLog)->with('status', 'Rifornimento registrato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FuelLog $fuelLog)
    {
        $fuelLog->load('vehicle', 'fuelCard');

        return view('admin.fuel-logs.show', compact('fuelLog'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FuelLog $fuelLog)
    {
        $vehicles = Vehicle::forCurrentUser()->get();
        $fuelCards = FuelCard::where('group_id', request()->user()->activeGroup()?->id)->get();

        return view('admin.fuel-logs.edit', compact('fuelLog', 'vehicles', 'fuelCards'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFuelLogRequest $request, FuelLog $fuelLog)
    {
        $fuelLog->update($request->validated());

        return redirect()->route('admin.fuel-logs.show', $fuelLog)->with('status', 'Rifornimento aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FuelLog $fuelLog)
    {
        $this->authorize('delete', $fuelLog);
        $fuelLog->delete();

        return redirect()->route('admin.fuel-logs.index')->with('status', 'Rifornimento eliminato con successo.');
    }
}
