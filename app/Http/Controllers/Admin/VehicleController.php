<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vehicles = Vehicle::query()
            ->with('vehicleType')
            ->withCount([
                'issues as open_issues_count' => fn($query) => $query->where('status', 'open'),
                'issues as in_progress_issues_count' => fn($query) => $query->where('status', 'in_progress'),
            ])
            ->get();

        return view('admin.vehicles.index', compact('vehicles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicleTypes = VehicleType::all();
        $vehicleBrands = Brand::pluck('name')->toArray();
        $vehicleModels = CarModel::pluck('name')->toArray();
        return view('admin.vehicles.create', compact('vehicleTypes', 'vehicleBrands', 'vehicleModels'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {

        $data = $request->validated();

        $hasWarrantyExtension = $request->boolean('has_warranty_extension');
        $warrantyOriginalExpirationDate = $data['warranty_expiration_date'] ?? null;
        $warrantyExtensionDuration = $hasWarrantyExtension ? (int) ($data['warranty_extension_duration'] ?? 0) : null;
        $warrantyEffectiveExpirationDate = $warrantyOriginalExpirationDate;

        // Se è presente estensione, salviamo la scadenza effettiva già estesa.
        if ($hasWarrantyExtension && $warrantyOriginalExpirationDate && $warrantyExtensionDuration) {
            $warrantyEffectiveExpirationDate = Carbon::parse($warrantyOriginalExpirationDate)
                ->addMonths($warrantyExtensionDuration)
                ->toDateString();
        }

        $vehicleData = [
            'license_plate' => $data['license_plate'],
            'vehicle_type_id' => $data['vehicle_type_id'],
            'internal_code' => $data['internal_code'],
            'brand' => $data['brand'],
            'model' => $data['model'],
            'fuel_type' => $data['fuel_type'] ?? null,
            'immatricolation_date' => $data['immatricolation_date'],
            'has_warranty_extension' => $hasWarrantyExtension,
            'warranty_extension_duration' => $warrantyExtensionDuration,
            'warranty_expiration_date' => $warrantyEffectiveExpirationDate,
        ];

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $vehicleData['registration_card_path'] = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $newVehicle = Vehicle::create($vehicleData);

        return redirect()->route('admin.vehicles.index')->with('status', 'Veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $vehicleAppointments = $vehicle->maintenanceRecords()
            ->with('issue', 'provider')
            ->orderByDesc('appointment_date')
            ->get();

        $deadlines = Deadline::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get()
            // Mostra in dettaglio solo la scadenza più recente per ogni tipologia.
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

        $hasWarrantyExtension = $request->boolean('has_warranty_extension');
        $warrantyOriginalExpirationDate = $data['warranty_expiration_date'] ?? null;
        $warrantyExtensionDuration = $hasWarrantyExtension ? (int) ($data['warranty_extension_duration'] ?? 0) : null;
        $warrantyEffectiveExpirationDate = $warrantyOriginalExpirationDate;

        if ($hasWarrantyExtension && $warrantyOriginalExpirationDate && $warrantyExtensionDuration) {
            $warrantyEffectiveExpirationDate = Carbon::parse($warrantyOriginalExpirationDate)
                ->addMonths($warrantyExtensionDuration)
                ->toDateString();
        }

        $vehicle->update([
            'license_plate' => $data['license_plate'],
            'vehicle_type_id' => $data['vehicle_type_id'],
            'internal_code' => $data['internal_code'],
            'brand' => $data['brand'],
            'model' => $data['model'],
            'fuel_type' => $data['fuel_type'] ?? null,
            'immatricolation_date' => $data['immatricolation_date'],
            'warranty_expiration_date' => $warrantyEffectiveExpirationDate,
            'has_warranty_extension' => $hasWarrantyExtension,
            'warranty_extension_duration' => $warrantyExtensionDuration,
        ]);

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
