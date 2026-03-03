<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
  
        return view('admin.vehicles.create', compact('vehicleTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->merge([
            'license_plate' => strtoupper(str_replace(' ', '', (string) $request->input('license_plate'))),
            'has_warranty_extension' => $request->boolean('has_warranty_extension'),
        ]);

        $data = $request->validate([
            'license_plate' => 'required|string|size:7|regex:/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/|unique:vehicles,license_plate',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'internal_code' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'fuel_type' => 'nullable|in:benzina,diesel,elettrico,ibrido',
            'immatricolation_date' => 'required|date',
            'registration_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'has_warranty_extension' => 'nullable|boolean',
            'warranty_expiration_date' => 'nullable|date|required_if_accepted:has_warranty_extension',
            'warranty_extension_duration' => 'nullable|integer|min:1|required_if_accepted:has_warranty_extension',
        ],
        [
            'license_plate.required' => 'La targa è obbligatoria.',
            'license_plate.size' => 'La targa deve avere 7 caratteri.',
            'license_plate.regex' => 'La targa deve essere nel formato AA123AA.',
            'license_plate.unique' => 'La targa deve essere unica.',
            'vehicle_type_id.required' => 'Il tipo di veicolo è obbligatorio.',
            'vehicle_type_id.exists' => 'Il tipo di veicolo selezionato non esiste.',
            'internal_code.required' => 'La sigla è obbligatoria.',
            'internal_code.size' => 'La sigla deve avere 4 cifre.',
            'internal_code.regex' => 'La sigla deve contenere solo 4 cifre.',
            'brand.required' => 'La marca è obbligatoria.',
            'model.required' => 'Il modello è obbligatorio.',
            'fuel_type.in' => 'Il tipo carburante selezionato non è valido.',
            'immatricolation_date.required' => "La data di immatricolazione è obbligatoria.",
            'registration_card.file' => "La carta di circolazione deve essere un file.",
            'registration_card.mimes' => "La carta di circolazione deve essere un file PDF, JPG, JPEG o PNG.",
            'has_warranty_extension.boolean' => "Il campo di estensione della garanzia deve essere un valore booleano.",
            'warranty_expiration_date.required_if_accepted' => "La data di scadenza è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_expiration_date.date' => "La data di scadenza originale della garanzia deve essere una data valida.",
            'warranty_extension_duration.required_if_accepted' => "La durata estensione è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_extension_duration.integer' => "La durata dell'estensione della garanzia deve essere un numero intero.",
            'warranty_extension_duration.min' => "La durata dell'estensione della garanzia deve essere almeno di 1 mese."
        ]
        );

        $hasWarrantyExtension = $request->boolean('has_warranty_extension');
        $warrantyOriginalExpirationDate = $data['warranty_expiration_date'] ?? null;
        $warrantyExtensionDuration = $hasWarrantyExtension ? (int) ($data['warranty_extension_duration'] ?? 0) : null;
        $warrantyEffectiveExpirationDate = $warrantyOriginalExpirationDate;

        if ($hasWarrantyExtension && $warrantyOriginalExpirationDate && $warrantyExtensionDuration) {
            $warrantyEffectiveExpirationDate = Carbon::parse($warrantyOriginalExpirationDate)
                ->addMonths($warrantyExtensionDuration)
                ->toDateString();
        }

        $newVehicle = new Vehicle();
        $newVehicle->license_plate = $data['license_plate'];
        $newVehicle->vehicle_type_id = $data['vehicle_type_id'];
        $newVehicle->internal_code = $data['internal_code'];
        $newVehicle->brand = $data['brand'];
        $newVehicle->model = $data['model'];
        $newVehicle->fuel_type = $data['fuel_type'] ?? null;
        $newVehicle->immatricolation_date = $data['immatricolation_date'];
        $newVehicle->has_warranty_extension = $hasWarrantyExtension;
        $newVehicle->warranty_extension_duration = $warrantyExtensionDuration;
        $newVehicle->warranty_expiration_date = $warrantyEffectiveExpirationDate;

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $newVehicle->registration_card_path = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $newVehicle->save();

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
            ->groupBy('type')
            ->map(fn ($typeDeadlines) => $typeDeadlines->first());
        $deadlinesTypes = ["revisione"=>Deadline::TYPE_MINISTERIAL, "ossigeno"=>Deadline::TYPE_OXYGEN];

        return view('admin.vehicles.show', compact('vehicle', 'vehicleAppointments', 'deadlines', 'deadlinesTypes'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicleTypes = VehicleType::all();

        $warrantyOriginalExpirationDate = $vehicle->warranty_expiration_date;
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
    public function update(Request $request, Vehicle $vehicle)
    {
        $request->merge([
            'license_plate' => strtoupper(str_replace(' ', '', (string) $request->input('license_plate'))),
            'has_warranty_extension' => $request->boolean('has_warranty_extension'),
        ]);

        $data = $request->validate(
            [
            'license_plate' => 'required|string|size:7|regex:/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/|unique:vehicles,license_plate,' . $vehicle->id,
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'internal_code' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'fuel_type' => 'nullable|in:benzina,diesel,elettrico,ibrido',
            'immatricolation_date' => 'required|date',
            'registration_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'has_warranty_extension' => 'nullable|boolean',
            'warranty_expiration_date' => 'nullable|date|required_if_accepted:has_warranty_extension',
            'warranty_extension_duration' => 'nullable|integer|min:1|required_if_accepted:has_warranty_extension',
        ],
        [
            'license_plate.required' => 'La targa è obbligatoria.',
            'license_plate.size' => 'La targa deve avere 7 caratteri.',
            'license_plate.regex' => 'La targa deve essere nel formato AA123AA.',
            'license_plate.unique' => 'La targa deve essere unica.',
            'vehicle_type_id.required' => 'Il tipo di veicolo è obbligatorio.',
            'vehicle_type_id.exists' => 'Il tipo di veicolo selezionato non esiste.',
            'internal_code.required' => 'La sigla è obbligatoria.',
            'internal_code.size' => 'La sigla deve avere 4 cifre.',
            'internal_code.regex' => 'La sigla deve contenere solo 4 cifre.',
            'brand.required' => 'La marca è obbligatoria.',
            'model.required' => 'Il modello è obbligatorio.',
            'fuel_type.in' => 'Il tipo carburante selezionato non è valido.',
            'immatricolation_date.required' => "La data di immatricolazione è obbligatoria.",
            'registration_card.file' => "La carta di circolazione deve essere un file.",
            'registration_card.mimes' => "La carta di circolazione deve essere un file PDF, JPG, JPEG o PNG.",
            'has_warranty_extension.boolean' => "Il campo di estensione della garanzia deve essere un valore booleano.",
            'warranty_expiration_date.required_if_accepted' => "La data di scadenza è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_expiration_date.date' => "La data di scadenza originale della garanzia deve essere una data valida.",
            'warranty_extension_duration.required_if_accepted' => "La durata estensione è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_extension_duration.integer' => "La durata dell'estensione della garanzia deve essere un numero intero.",
            'warranty_extension_duration.min' => "La durata dell'estensione della garanzia deve essere almeno di 1 mese."
        ]
        );

        $hasWarrantyExtension = $request->boolean('has_warranty_extension');
        $warrantyOriginalExpirationDate = $data['warranty_expiration_date'] ?? null;
        $warrantyExtensionDuration = $hasWarrantyExtension ? (int) ($data['warranty_extension_duration'] ?? 0) : null;
        $warrantyEffectiveExpirationDate = $warrantyOriginalExpirationDate;

        if ($hasWarrantyExtension && $warrantyOriginalExpirationDate && $warrantyExtensionDuration) {
            $warrantyEffectiveExpirationDate = Carbon::parse($warrantyOriginalExpirationDate)
                ->addMonths($warrantyExtensionDuration)
                ->toDateString();
        }

        $vehicle->license_plate = $data['license_plate'];
        $vehicle->vehicle_type_id = $data['vehicle_type_id'];
        $vehicle->internal_code = $data['internal_code'];
        $vehicle->brand = $data['brand'];
        $vehicle->model = $data['model'];
        $vehicle->fuel_type = $data['fuel_type'] ?? null;
        $vehicle->immatricolation_date = $data['immatricolation_date'];
        $vehicle->warranty_expiration_date = $warrantyEffectiveExpirationDate;
        $vehicle->has_warranty_extension = $hasWarrantyExtension;
        $vehicle->warranty_extension_duration = $warrantyExtensionDuration;

        $vehicle->update();

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
