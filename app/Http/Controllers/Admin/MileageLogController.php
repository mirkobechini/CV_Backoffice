<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Requests\StoreMileageLogRequest;
use App\Http\Requests\UpdateMileageLogRequest;
use App\Models\MileageLog;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class MileageLogController extends Controller
{
    use SortableAndGroupable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:vehicle,date,km',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $mileageLogs = $this->applySorting(
            MileageLog::with('vehicle'),
            $sortBy,
            $sortDir,
            [
                'vehicle' => fn(MileageLog $l) => $l->vehicle?->internal_code ?? '',
                'date' => 'log_date',
                'km' => 'mileage',
            ]
        );

        return view('admin.mileage-logs.index', compact('mileageLogs', 'sortBy', 'sortDir') + [
            'sortToggleUrl' => fn($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.mileage-logs.index'),
            'sortIcon' => fn($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        return view('admin.mileage-logs.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMileageLogRequest $request)
    {
        $newMileageLog = MileageLog::create($request->validated());
        return redirect()->route('admin.mileage-logs.show', $newMileageLog)->with('status', 'Chilometraggio creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MileageLog $mileageLog)
    {
        $mileageLog->load('vehicle');
        return view('admin.mileage-logs.show', compact('mileageLog'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MileageLog $mileageLog)
    {
        $vehicles = Vehicle::all();
        return view('admin.mileage-logs.edit', compact('mileageLog', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMileageLogRequest $request, MileageLog $mileageLog)
    {
        $mileageLog->update($request->validated());
        return redirect()->route('admin.mileage-logs.show',  $mileageLog)->with('status', 'Chilometraggio aggiornato con successo.');
    }

    /**
     * Show the form for bulk mileage entry (all vehicles).
     */
    public function bulkCreate()
    {
        $vehicles = Vehicle::with(['brand', 'carModel'])->orderBy('internal_code')->get();
        return view('admin.mileage-logs.bulk', compact('vehicles'));
    }

    /**
     * Store bulk mileage logs for multiple vehicles at once.
     */
    public function bulkStore(Request $request)
    {
        $this->authorize('create', MileageLog::class);
        $data = $request->validate([
            'log_date' => 'required|date',
            'mileages' => 'nullable|array',
            'mileages.*' => 'nullable|integer|min:0',
        ]);

        $logDate = $data['log_date'];
        $count = 0;
        $errors = [];

        if (!empty($data['mileages'])) {
            $validVehicleIds = Vehicle::whereIn('id', array_keys($data['mileages']))
                ->pluck('id')
                ->toArray();

            // Pre-carica l'ultimo km per ogni veicolo coinvolto
            $lastMileages = MileageLog::whereIn('vehicle_id', $validVehicleIds)
                ->selectRaw('vehicle_id, MAX(mileage) as last_mileage')
                ->groupBy('vehicle_id')
                ->pluck('last_mileage', 'vehicle_id');

            foreach ($data['mileages'] as $vehicleId => $mileage) {
                if ($mileage === null || $mileage === '' || $mileage < 0) {
                    continue;
                }

                $vehicleId = (int) $vehicleId;

                if (!in_array($vehicleId, $validVehicleIds, true)) {
                    continue;
                }

                // Validazione: km devono essere >= ultimo registrato
                $lastKm = $lastMileages[$vehicleId] ?? null;
                if ($lastKm !== null && (int) $mileage < (int) $lastKm) {
                    $vehicle = Vehicle::find($vehicleId);
                    $label = $vehicle ? ($vehicle->internal_code . ' - ' . $vehicle->license_plate) : ('ID ' . $vehicleId);
                    $errors[] = "{$label}: {$mileage} km è inferiore all'ultimo registrato ({$lastKm} km).";
                    continue;
                }

                MileageLog::create([
                    'vehicle_id' => $vehicleId,
                    'log_date' => $logDate,
                    'mileage' => (int) $mileage,
                ]);

                $count++;
            }
        }

        if (!empty($errors)) {
            return redirect()->route('admin.mileage-logs.bulk')
                ->with('status_error', 'Alcuni chilometraggi non sono stati registrati:')
                ->with('status_errors', $errors)
                ->withInput();
        }

        if ($count === 0) {
            return redirect()->route('admin.mileage-logs.bulk')
                ->with('status', 'Nessun chilometraggio da registrare (campi vuoti).')
                ->withInput();
        }

        return redirect()->route('admin.mileage-logs.index')
            ->with('status', "{$count} chilometraggi registrati con successo per il {$logDate}.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MileageLog $mileageLog)
    {
        $this->authorize('delete', $mileageLog);
        $mileageLog->delete();
        return redirect()->route('admin.mileage-logs.index')->with('status', 'Chilometraggio eliminato con successo.');
    }

    /**
     * Elimina multipli chilometraggi selezionati.
     */
    public function bulkDelete(Request $request)
    {
        $this->authorize('delete', MileageLog::class);
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:mileage_logs,id',
        ]);

        $count = MileageLog::whereIn('id', $data['ids'])->delete();

        return redirect()->route('admin.mileage-logs.index')
            ->with('status', "{$count} chilometraggi eliminati con successo.");
    }

    /**
     * Vista mensile pivot (come nel foglio Google).
     */
    public function pivot(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $vehicles = Vehicle::with(['brand', 'carModel'])->orderBy('internal_code')->get();

        // Raccogli i km per ogni veicolo per ogni mese dell'anno.
        // Estraiamo il mese in PHP (via Carbon) per restare portabili su
        // qualsiasi DB (MONTH() è MySQL-specifico e fallisce su SQLite).
        $logs = MileageLog::whereYear('log_date', $year)
            ->get(['vehicle_id', 'log_date', 'mileage'])
            ->groupBy('vehicle_id')
            ->map(function ($items) {
                return $items->keyBy(fn($log) => $log->log_date->month);
            });

        return view('admin.mileage-logs.pivot', compact('vehicles', 'logs', 'year'));
    }

    /**
     * Salva modifiche dalla vista pivot.
     */
    public function pivotSave(Request $request)
    {
        $this->authorize('create', MileageLog::class);
        $data = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'mileages' => 'nullable|array',
            'mileages.*' => 'nullable|array',
            'mileages.*.*' => 'nullable|integer|min:0',
        ]);

        // Valida che le chiavi vehicle_id esistano davvero, per evitare
        // errori di foreign key (500) con ID inesistenti.
        if (!empty($data['mileages'])) {
            $validVehicleIds = Vehicle::whereIn('id', array_keys($data['mileages']))
                ->pluck('id')
                ->toArray();
        }

        $year = $data['year'];
        $count = 0;

        if (!empty($data['mileages'])) {
            foreach ($data['mileages'] as $vehicleId => $months) {
                // Salta veicoli non validi (ID inesistente)
                if (!in_array((int) $vehicleId, $validVehicleIds, true)) {
                    continue;
                }

                foreach ($months as $month => $mileage) {
                    if ($mileage === null || $mileage === '' || $mileage < 0) {
                        continue;
                    }

                    $dateStr = sprintf('%04d-%02d-01', $year, $month);

                    MileageLog::updateOrCreate(
                        ['vehicle_id' => $vehicleId, 'log_date' => $dateStr],
                        ['mileage' => (int) $mileage]
                    );

                    $count++;
                }
            }
        }

        return redirect()->route('admin.mileage-logs.pivot', ['year' => $year])
            ->with('status', "{$count} chilometraggi salvati con successo.");
    }
}
