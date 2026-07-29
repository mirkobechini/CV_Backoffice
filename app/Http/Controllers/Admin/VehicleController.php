<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $vehicles = Vehicle::query()
            ->with(['vehicleType.equipmentTypes', 'brand', 'carModel', 'equipment'])
            ->withCount([
                'issues as open_issues_count' => fn($query) => $query->where('status', 'open'),
                'issues as in_progress_issues_count' => fn($query) => $query->where('status', 'in_progress'),
            ])
            ->paginate(20);

        return view('admin.vehicles.index', compact('vehicles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicleTypes = VehicleType::all();
        return view('admin.vehicles.create', compact('vehicleTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {

        $data = $request->validated();

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $data['registration_card_path'] = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $newVehicle = Vehicle::create($data);

        return redirect()->route('admin.vehicles.show', $newVehicle)->with('status', 'Veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['vehicleType', 'brand', 'carModel', 'equipment.equipmentType', 'issues', 'deadlines', 'mileageLogs']);

        $vehicleAppointments = $vehicle->maintenanceRecords()
            ->with('issue', 'provider')
            ->orderByDesc('appointment_date')
            ->get();

        $deadlines = $vehicle->deadlines
            ->sortByDesc('due_date')
            ->groupBy('type')
            ->map(fn($typeDeadlines) => $typeDeadlines->first());
        $deadlinesTypes = ["revisione" => Deadline::TYPE_MINISTERIAL, "ossigeno" => Deadline::TYPE_OXYGEN];

        return view('admin.vehicles.show', compact('vehicle', 'vehicleAppointments', 'deadlines', 'deadlinesTypes'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicleTypes = VehicleType::all();

        $warrantyOriginalExpirationDate = $vehicle->warranty_expiration_date?->toDateString();
        if ($vehicle->has_warranty_extension && $vehicle->warranty_expiration_date && $vehicle->warranty_extension_duration) {
            $warrantyOriginalExpirationDate = Carbon::parse($vehicle->warranty_expiration_date)
                ->subMonths((int) $vehicle->warranty_extension_duration)
                ->toDateString();
        }

        return view('admin.vehicles.edit', compact('vehicle', 'vehicleTypes', 'warrantyOriginalExpirationDate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {

        $data = $request->validated();

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $data['registration_card_path'] = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $vehicle->update($data);

        return redirect()->route('admin.vehicles.show', $vehicle->id)->with('status', 'Veicolo aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('admin.vehicles.index')->with('status', 'Veicolo eliminato con successo.');
    }
}
