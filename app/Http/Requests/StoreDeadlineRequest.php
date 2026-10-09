<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeadlineRequest extends FormRequest
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
            'insurance_company' => 'nullable|string|max:255|required_if:type,Assicurazione',
            'insurance_policy_number' => 'nullable|string|max:255|required_if:type,Assicurazione',
            'coverages' => ['required_if:type,Assicurazione', 'array'],
            'coverages.*.cost' => 'required|numeric|min:0',
            'insurance_coverage_limit' => 'nullable|numeric|min:0',
            'insurance_broker_contact' => 'nullable|string|max:255',
            'insurance_renewal_months' => 'nullable|integer|min:1|max:120',
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
            'insurance_company.required_if' => 'La compagnia è obbligatoria per le assicurazioni.',
            'insurance_policy_number.max' => 'Il numero di polizza non può superare 255 caratteri.',
            'insurance_policy_number.required_if' => 'Il numero di polizza è obbligatorio per le assicurazioni.',
            'coverages.required_if' => 'Seleziona almeno una copertura per le assicurazioni.',
            'coverages.*.cost.required' => 'Indica il costo per ogni copertura selezionata.',
            'coverages.*.cost.numeric' => 'Il costo della copertura deve essere un numero.',
            'coverages.*.cost.min' => 'Il costo della copertura non può essere negativo.',
            'insurance_coverage_limit.numeric' => 'Il massimale deve essere un numero.',
            'insurance_coverage_limit.min' => 'Il massimale non può essere negativo.',
            'insurance_broker_contact.max' => 'Il contatto broker/agenzia non può superare 255 caratteri.',
            'insurance_renewal_months.integer' => 'La durata del rinnovo deve essere un numero di mesi.',
            'insurance_renewal_months.min' => 'La durata del rinnovo deve essere di almeno 1 mese.',
            'insurance_renewal_months.max' => 'La durata del rinnovo non può superare 120 mesi.',
            'notes.max' => 'Le note non possono superare 2000 caratteri.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $coverages = $this->input('coverages', []);

            if (! is_array($coverages)) {
                return;
            }

            // Le chiavi arrivano dal nome del checkbox/campo nel form
            // (coverages[RCA][cost]): devono essere nel vocabolario fisso,
            // non testo libero come prima della migrazione a select.
            foreach (array_keys($coverages) as $type) {
                if (! in_array($type, Deadline::INSURANCE_COVERAGE_TYPES, true)) {
                    $validator->errors()->add('coverages', 'Una o più coperture selezionate non sono valide.');
                    break;
                }
            }
        });
    }
}
