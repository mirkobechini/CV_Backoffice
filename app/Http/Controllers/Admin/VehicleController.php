<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\vehicleType;
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
        $vehicles = Vehicle::all()->load('vehicleType'); // Carica la relazione 'vehicle_type' per ogni veicolo
        return view('admin.vehicles.index', compact('vehicles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicleTypes = vehicleType::all();
  
        return view('admin.vehicles.create', compact('vehicleTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'license_plate' => 'required|string|size:7|regex:/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/|unique:vehicles,license_plate',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'internal_code' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'immatricolation_date' => 'required|date',
            'registration_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'has_warranty_extension' => 'nullable|boolean',
            'warranty_original_expiration_date' => 'nullable|date|required_if_accepted:has_warranty_extension',
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
            'immatricolation_date.required' => "La data di immatricolazione è obbligatoria.",
            'registration_card.file' => "La carta di circolazione deve essere un file.",
            'registration_card.mimes' => "La carta di circolazione deve essere un file PDF, JPG, JPEG o PNG.",
            'has_warranty_extension.boolean' => "Il campo di estensione della garanzia deve essere un valore booleano.",
            'warranty_original_expiration_date.required_if_accepted' => "La data di scadenza è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_original_expiration_date.date' => "La data di scadenza originale della garanzia deve essere una data valida.",
            'warranty_extension_duration.required_if_accepted' => "La durata estensione è obbligatoria quando l'estensione garanzia è attiva.",
            'warranty_extension_duration.integer' => "La durata dell'estensione della garanzia deve essere un numero intero.",
            'warranty_extension_duration.min' => "La durata dell'estensione della garanzia deve essere almeno di 1 mese."
        ]
        );

        $hasWarrantyExtension = $request->boolean('has_warranty_extension');

        $newVehicle = new Vehicle();
        $newVehicle->license_plate = $data['license_plate'];
        $newVehicle->vehicle_type_id = $data['vehicle_type_id'];
        $newVehicle->internal_code = $data['internal_code'];
        $newVehicle->brand = $data['brand'];
        $newVehicle->model = $data['model'];
        $newVehicle->immatricolation_date = $data['immatricolation_date'];
        $newVehicle->warranty_original_expiration_date = $data['warranty_original_expiration_date'] ?? null;
        $newVehicle->has_warranty_extension = $hasWarrantyExtension;
        $newVehicle->warranty_expiration_date = $data['warranty_original_expiration_date'] ?? null;

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $newVehicle->registration_card_path = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        if ($hasWarrantyExtension && !empty($data['warranty_original_expiration_date'])) {
            $newVehicle->warranty_expiration_date = Carbon::parse($data['warranty_original_expiration_date'])
                ->addMonths((int) $data['warranty_extension_duration'])
                ->toDateString();
        }

        $newVehicle->save();

        return redirect()->route('admin.vehicles.index')->with('status', 'Veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        return view('admin.vehicles.show', compact('vehicle'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        return view('admin.vehicles.edit', compact('vehicle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        //
    }
}
