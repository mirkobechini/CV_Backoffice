<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Vehicle::class, 'vehicle');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $vehicles = Vehicle::query()
            ->forCurrentUser()
            ->with(['vehicleType.equipmentTypes', 'brand', 'carModel', 'equipment', 'latestMileageLog'])
            ->with(['mileageLogs' => function ($query) {
                $query->orderByDesc('log_date')->limit(2);
            }])
            ->with(['deadlines' => function ($query) {
                $query->where('is_renewed', false)
                    ->orderBy('due_date');
            }])
            ->withCount([
                'issues as open_issues_count' => fn($query) => $query->where('status', 'open'),
                'issues as in_progress_issues_count' => fn($query) => $query->where('status', 'in_progress'),
            ])
            ->search($request->get('q'));

        // Filtri chip (Tutti, Guasti, Scadenza prossima, Da integrare)
        if ($filter === 'issues') {
            $vehicles = $vehicles->whereHas('issues', fn($q) => $q->whereIn('status', ['open', 'in_progress']));
        } elseif ($filter === 'deadline') {
            $vehicles = $vehicles->whereHas('deadlines', fn($q) => $q->where('is_renewed', false)->whereIn('status', ['pending', 'expired']));
        } elseif ($filter === 'incomplete') {
            $incompleteIds = Vehicle::forCurrentUser()
                ->with('vehicleType.equipmentTypes', 'equipment')
                ->get()
                ->reject(fn($v) => $v->hasAllRequiredEquipment())
                ->pluck('id');
            $vehicles = $vehicles->whereIn('id', $incompleteIds);
        }

        $vehicles = $vehicles->paginate(20);

        // Stats per la toolbar
        $totalVehicles = Vehicle::forCurrentUser()->count();
        $openIssuesCount = Vehicle::forCurrentUser()
            ->withCount(['issues as c' => fn($q) => $q->whereIn('status', ['open', 'in_progress'])])
            ->get()
            ->sum('c');
        $deadlinesIn30 = Deadline::where('is_renewed', false)
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser())
            ->upcoming(30)
            ->count();
        $completeFleet = Vehicle::forCurrentUser()
            ->with('vehicleType.equipmentTypes', 'equipment')
            ->get()
            ->filter(fn($v) => $v->hasAllRequiredEquipment())
            ->count();

        return view('admin.vehicles.index', compact(
            'vehicles',
            'totalVehicles',
            'openIssuesCount',
            'deadlinesIn30',
            'completeFleet',
            'filter'
        ));
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
        $data['has_timing_belt'] = $request->boolean('has_timing_belt');

        // Assegna il veicolo al gruppo dell'utente autenticato.
        $data['group_id'] = $request->user()->activeGroup()?->id;

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
            ->with('items.itemable', 'provider')
            ->orderByDesc('appointment_date')
            ->get();

        $deadlines = $vehicle->deadlines_grouped;
        $deadlinesTypes = Vehicle::DEADLINE_TYPES;

        return view('admin.vehicles.show', compact('vehicle', 'vehicleAppointments', 'deadlines', 'deadlinesTypes'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicleTypes = VehicleType::all();
        $warrantyOriginalExpirationDate = $vehicle->warranty_original_expiration_date;

        return view('admin.vehicles.edit', compact('vehicle', 'vehicleTypes', 'warrantyOriginalExpirationDate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {

        $data = $request->validated();
        $data['has_timing_belt'] = $request->boolean('has_timing_belt');

        if ($request->hasFile('registration_card')) {
            // Elimina il file precedente per evitare leak di storage
            if ($vehicle->registration_card_path) {
                Storage::disk('public')->delete($vehicle->registration_card_path);
            }

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
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('status', 'Veicolo eliminato con successo.');
    }
}
