<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMileageLogRequest;
use App\Http\Requests\UpdateMileageLogRequest;
use App\Models\MileageLog;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class MileageLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $mileageLogs = MileageLog::query()
            ->with('vehicle')
            ->orderByDesc('log_date')
            ->paginate(20);

        return view('admin.mileage-logs.index', compact('mileageLogs'));
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
        $mileageLog->delete();
        return redirect()->route('admin.mileage-logs.index')->with('status', 'Chilometraggio eliminato con successo.');
    }
}
