<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeadlineRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno',
            'due_date' => 'nullable|date_format:Y-m|required_unless:type,Revisione Ministeriale,Revisione Impianto Ossigeno',
            'mark_as_renewed' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'type.required' => 'La tipologia è obbligatoria.',
            'type.in' => 'La tipologia selezionata non è valida.',
            'due_date.required' => 'La data di scadenza è obbligatoria.',
            'due_date.required_unless' => 'La data di scadenza è obbligatoria per questa tipologia.',
            'due_date.date_format' => 'La data di scadenza deve essere nel formato mese/anno valido.',
            'mark_as_renewed.boolean' => 'Il valore di rinnovo non è valido.',
        ];
    }
}
