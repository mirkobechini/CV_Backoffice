<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelLogRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'log_date' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.01'],
            'cost_total' => ['nullable', 'numeric', 'min:0'],
            'mileage' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'log_date.required' => 'Il campo data è obbligatorio.',
            'log_date.date' => 'Il campo data deve essere una data valida.',
            'liters.required' => 'Il campo litri è obbligatorio.',
            'liters.numeric' => 'Il campo litri deve essere un numero.',
            'liters.min' => 'Il campo litri deve essere maggiore di zero.',
            'cost_total.numeric' => 'Il campo costo totale deve essere un numero.',
            'cost_total.min' => 'Il costo totale non può essere negativo.',
            'mileage.integer' => 'Il campo chilometraggio deve essere un numero intero.',
            'mileage.min' => 'Il chilometraggio non può essere negativo.',
        ];
    }
}
