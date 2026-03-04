<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMileageLogRequest extends FormRequest
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
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'log_date' => ['required', 'date'],
            'mileage' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'log_date.required' => 'Il campo data del registro è obbligatorio.',
            'log_date.date' => 'Il campo data del registro deve essere una data valida.',
            'mileage.required' => 'Il campo chilometraggio è obbligatorio.',
            'mileage.integer' => 'Il campo chilometraggio deve essere un numero intero.',
            'mileage.min' => 'Il chilometraggio non può essere negativo.',
        ];
    }
}