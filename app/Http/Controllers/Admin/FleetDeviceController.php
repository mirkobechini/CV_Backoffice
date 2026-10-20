<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFleetDeviceAssignmentRequest;
use App\Http\Requests\StoreFleetDeviceRequest;
use App\Http\Requests\UpdateFleetDeviceRequest;
use App\Models\FleetDevice;
use App\Models\FleetDeviceAssignment;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class FleetDeviceController extends Controller
{
    use SortableAndGroupable;

    public function __construct()
    {
        $this->authorizeResource(FleetDevice::class, 'fleetDevice');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:vehicle,type,code',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $sortBy = $validated['sort_by'] ?? 'type';
        $sortDir = $validated['sort_dir'] ?? 'asc';

        $groupId = $request->user()->activeGroup()?->id;

        $fleetDevices = $this->applySorting(
            FleetDevice::with('vehicle')->where('group_id', $groupId),
            $sortBy,
            $sortDir,
            [
                'vehicle' => fn (FleetDevice $d) => $d->vehicle?->internal_code ?? '',
                'type' => 'type',
                'code' => 'code',
            ]
        );

        return view('admin.fleet-devices.index', compact('fleetDevices', 'sortBy', 'sortDir') + [
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.fleet-devices.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::forCurrentUser()->get();

        return view('admin.fleet-devices.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage. Se viene indicato subito un
     * veicolo, registra anche la prima riga di storico (previous_vehicle_id
     * null): altrimenti lo storico partirebbe vuoto nonostante il
     * dispositivo risulti già assegnato fin dalla creazione.
     */
    public function store(StoreFleetDeviceRequest $request)
    {
        $data = $request->validated();
        $data['group_id'] = $request->user()->activeGroup()?->id;

        $fleetDevice = FleetDevice::create($data);

        if ($fleetDevice->vehicle_id) {
            FleetDeviceAssignment::create([
                'fleet_device_id' => $fleetDevice->id,
                'vehicle_id' => $fleetDevice->vehicle_id,
                'previous_vehicle_id' => null,
                'plate_snapshot' => $fleetDevice->vehicle?->license_plate,
                'assigned_date' => now(),
            ]);
        }

        return redirect()->route('admin.fleet-devices.show', $fleetDevice)->with('status', 'Dispositivo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FleetDevice $fleetDevice)
    {
        $fleetDevice->load('vehicle', 'assignments.vehicle', 'assignments.previousVehicle');
        $vehicles = Vehicle::forCurrentUser()->get();

        return view('admin.fleet-devices.show', compact('fleetDevice', 'vehicles'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FleetDevice $fleetDevice)
    {
        return view('admin.fleet-devices.edit', compact('fleetDevice'));
    }

    /**
     * Update the specified resource in storage (solo campi anagrafici: il
     * veicolo si cambia tramite reassign()).
     */
    public function update(UpdateFleetDeviceRequest $request, FleetDevice $fleetDevice)
    {
        $fleetDevice->update($request->validated());

        return redirect()->route('admin.fleet-devices.show', $fleetDevice)->with('status', 'Dispositivo aggiornato con successo.');
    }

    /**
     * Riassegna il dispositivo a un altro veicolo: registra la riga di
     * storico e aggiorna il veicolo attuale, stesso ruolo di
     * TireController::recordChange() per i cambi gomme.
     */
    public function reassign(StoreFleetDeviceAssignmentRequest $request, FleetDevice $fleetDevice)
    {
        $this->authorize('update', $fleetDevice);

        $data = $request->validated();
        $newVehicle = Vehicle::findOrFail($data['vehicle_id']);

        FleetDeviceAssignment::create([
            'fleet_device_id' => $fleetDevice->id,
            'vehicle_id' => $newVehicle->id,
            'previous_vehicle_id' => $fleetDevice->vehicle_id,
            'plate_snapshot' => $newVehicle->license_plate,
            'assigned_date' => $data['assigned_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        $fleetDevice->update(['vehicle_id' => $newVehicle->id]);

        return redirect()->route('admin.fleet-devices.show', $fleetDevice)->with('status', 'Dispositivo riassegnato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FleetDevice $fleetDevice)
    {
        $this->authorize('delete', $fleetDevice);
        $fleetDevice->delete();

        return redirect()->route('admin.fleet-devices.index')->with('status', 'Dispositivo eliminato con successo.');
    }
}
