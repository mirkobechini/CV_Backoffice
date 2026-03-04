<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleTypeRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:vehicle_types,name',
            'needs_oxygen_check' => 'boolean',
            'extinguishers_required' => 'required|integer|min:0',
            'first_inspection_months' => 'required|integer|min:0',
            'regular_inspection_months' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'name.string' => 'Il nome deve essere una stringa.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',
            'name.unique' => 'Esiste già un tipo di veicolo con questo nome.',
            'needs_oxygen_check.boolean' => 'Il campo revisione ossigeno deve essere true o false.',
            'extinguishers_required.required' => 'Il numero di estintori è obbligatorio.',
            'extinguishers_required.integer' => 'Il numero di estintori deve essere un intero.',
            'extinguishers_required.min' => 'Il numero di estintori non può essere negativo.',
            'first_inspection_months.required' => 'La durata della prima revisione è obbligatoria.',
            'first_inspection_months.integer' => 'La durata della prima revisione deve essere un intero.',
            'first_inspection_months.min' => 'La durata della prima revisione non può essere negativa.',
            'regular_inspection_months.required' => 'La durata delle revisioni successive è obbligatoria.',
            'regular_inspection_months.integer' => 'La durata delle revisioni successive deve essere un intero.',
            'regular_inspection_months.min' => 'La durata delle revisioni successive non può essere negativa.',
        ];
    }
}
