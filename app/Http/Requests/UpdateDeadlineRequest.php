<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeadlineRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno,Tagliando,Cinghia Distribuzione',
            'due_date' => 'nullable|date_format:Y-m|required_unless:type,Revisione Ministeriale,Revisione Impianto Ossigeno',
            'status' => 'nullable|in:pending,expired,renewed,valid',
            'is_renewed' => 'nullable|boolean',
            'interval_km' => 'nullable|integer|min:0',
            'last_mileage' => 'nullable|integer|min:0',
            'interval_days' => 'nullable|integer|min:0',
            'insurance_company' => 'nullable|string|max:255',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_premium' => 'nullable|numeric|min:0',
            'insurance_coverage_type' => 'nullable|string|max:255',
            'insurance_coverage_limit' => 'nullable|numeric|min:0',
            'insurance_broker_contact' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'type.required' => 'La tipologia è obbligatoria.',
            'type.in' => 'La tipologia selezionata non è valida.',
            'due_date.required' => 'La data di scadenza è obbligatoria.',
            'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
            'due_date.date_format' => 'La data di scadenza deve essere nel formato mese/anno valido.',
            'status.in' => 'Lo stato selezionato non è valido.',
            'is_renewed.boolean' => 'Il valore di rinnovo non è valido.',
            'interval_km.integer' => "L'intervallo km deve essere un numero.",
            'interval_km.min' => "L'intervallo km non può essere negativo.",
            'last_mileage.integer' => 'I km all\'ultimo cambio devono essere un numero.',
            'last_mileage.min' => 'I km all\'ultimo cambio non possono essere negativi.',
            'interval_days.integer' => "L'intervallo giorni deve essere un numero.",
            'interval_days.min' => "L'intervallo giorni non può essere negativo.",
            'insurance_company.max' => 'Il nome della compagnia non può superare 255 caratteri.',
            'insurance_policy_number.max' => 'Il numero di polizza non può superare 255 caratteri.',
            'insurance_premium.numeric' => 'Il premio annuo deve essere un numero.',
            'insurance_premium.min' => 'Il premio annuo non può essere negativo.',
            'insurance_coverage_type.max' => 'Il tipo di copertura non può superare 255 caratteri.',
            'insurance_coverage_limit.numeric' => 'Il massimale deve essere un numero.',
            'insurance_coverage_limit.min' => 'Il massimale non può essere negativo.',
            'insurance_broker_contact.max' => 'Il contatto broker/agenzia non può superare 255 caratteri.',
            'notes.max' => 'Le note non possono superare 2000 caratteri.',
        ];
    }
}
