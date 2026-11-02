<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\FuelCard;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'fuel_card_id' => ['nullable', new BelongsToCurrentUserGroup(FuelCard::class, message: 'La tessera selezionata non esiste o non appartiene al tuo gruppo.', byOwnGroupColumn: true)],
            'fuel_type' => ['required', 'in:benzina,diesel,elettrico,metano'],
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
            'fuel_type.required' => 'Il campo carburante è obbligatorio.',
            'fuel_type.in' => 'Il tipo carburante selezionato non è valido.',
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

    /**
     * Il carburante deve essere tra quelli ammessi dal veicolo scelto
     * (Vehicle::allowedFuelLogTypes()), non una qualunque delle 4 opzioni —
     * stesso filtro già applicato in UI, qui solo per non fidarsi di un
     * form manomesso. Permissivo se il veicolo non ha ancora nessun
     * carburante configurato (stesso fallback della UI), per non bloccare
     * la registrazione di un rifornimento solo perché l'anagrafica non è
     * stata completata.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('vehicle_id') || $validator->errors()->has('fuel_type')) {
                return;
            }

            $vehicle = Vehicle::find($this->input('vehicle_id'));
            $allowed = $vehicle?->allowedFuelLogTypes() ?? [];

            if ($allowed && ! in_array($this->input('fuel_type'), $allowed, true)) {
                $validator->errors()->add('fuel_type', 'Il carburante selezionato non è tra quelli ammessi per questo veicolo.');
            }
        });
    }
}
