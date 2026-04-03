<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'license_plate' => 'required|string|size:7|regex:/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/|unique:vehicles,license_plate',
            'vehicle_type_id' => 'required|exists:vehicle_types,id',
            'internal_code' => 'required|string|size:4|regex:/^[0-9]{4}$/',
            'brand_id' => 'required|exists:brands,id',
            'car_model_id' => 'required|exists:car_models,id|car_model_belongs_to_brand:brand_id',
            'fuel_type' => 'nullable|in:benzina,diesel,elettrico,ibrido',
            'immatricolation_date' => 'required|date',
            'registration_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'has_warranty_extension' => 'nullable|boolean',
            'warranty_expiration_date' => 'nullable|date|required_if_accepted:has_warranty_extension',
            'warranty_extension_duration' => 'nullable|integer|min:1|required_if_accepted:has_warranty_extension',
        ];
    }

    public function messages(): array
    {
        return [
            'license_plate.required' => 'La targa è obbligatoria.',
            'license_plate.size' => 'La targa deve avere 7 caratteri.',
            'license_plate.regex' => 'La targa deve essere nel formato AA123AA.',
            'license_plate.unique' => 'La targa deve essere unica.',
            'vehicle_type_id.required' => 'Il tipo di veicolo è obbligatorio.',
            'vehicle_type_id.exists' => 'Il tipo di veicolo selezionato non esiste.',
            'internal_code.required' => 'La sigla è obbligatoria.',
            'internal_code.size' => 'La sigla deve avere 4 cifre.',
            'internal_code.regex' => 'La sigla deve contenere solo 4 cifre.',
            'brand_id.required' => 'La marca è obbligatoria.',
            'brand_id.exists' => 'La marca selezionata non esiste.',
            'car_model_id.required' => 'Il modello è obbligatorio.',
            'car_model_id.exists' => 'Il modello selezionato non esiste.',
            'car_model_id.car_model_belongs_to_brand' => 'Il modello selezionato non appartiene alla marca specificata.',
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
        ];
    }

    // Normalizzazione input prima della validate:
    // targa in formato canonico e checkbox in booleano reale.
    public function prepareForValidation(): void
    {
        $this->merge([
            'license_plate' => strtoupper(str_replace(' ', '', (string) $this->input('license_plate'))),
            'has_warranty_extension' => $this->boolean('has_warranty_extension'),
        ]);
    }
}
