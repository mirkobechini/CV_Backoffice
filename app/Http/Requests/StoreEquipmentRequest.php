<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEquipmentRequest extends FormRequest
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
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'unique:equipment,serial_number|nullable|string|max:255',
            'identification_number' => 'nullable|string|max:255',
            'fabrication_date' => 'nullable|date',
            'revision_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:revision_date',
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'extinguisher_agent' => 'nullable|in:co2,powder',
            'weight_kg' => 'nullable|numeric|min:0|max:999',
            'collaudo_date' => 'nullable|date',
            'next_collaudo_date' => 'nullable|date|after_or_equal:collaudo_date',
            'chair_type' => 'nullable|in:electric,manual_2_wheel,manual_4_wheel,manual_tracks',
            'max_weight_kg' => 'nullable|numeric|min:0|max:9999',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'name.required' => 'Il campo nome è obbligatorio.',
            'name.string' => 'Il campo nome deve essere una stringa.',
            'name.max' => 'Il campo nome non può superare i 255 caratteri.',
            'serial_number.unique' => 'Il campo numero di serie deve essere univoco.',
            'serial_number.string' => 'Il campo numero di serie deve essere una stringa.',
            'serial_number.max' => 'Il campo numero di serie non può superare i 255 caratteri.',
            'revision_date.date' => 'Il campo data di revisione deve essere una data valida.',
            'expiration_date.date' => 'Il campo data di scadenza deve essere una data valida.',
            'expiration_date.after_or_equal' => 'Il campo data di scadenza deve essere successivo o uguale alla data di revisione.',
            'equipment_type_id.required' => 'Il campo tipo di attrezzatura è obbligatorio.',
            'equipment_type_id.exists' => 'Il tipo di attrezzatura selezionato non esiste.',
            'fabrication_date.date' => 'La data di fabbricazione deve essere una data valida.',
            'extinguisher_agent.in' => "L'agente estinguente selezionato non è valido.",
            'weight_kg.numeric' => 'Il peso deve essere un numero.',
            'collaudo_date.date' => 'La data di collaudo deve essere una data valida.',
            'next_collaudo_date.date' => 'La data del prossimo collaudo deve essere una data valida.',
            'next_collaudo_date.after_or_equal' => 'La data del prossimo collaudo deve essere successiva o uguale alla data di collaudo.',
            'chair_type.in' => 'Il tipo di sedia selezionato non è valido.',
            'max_weight_kg.numeric' => 'Il peso massimo deve essere un numero.',
            'notes.max' => 'Le note non possono superare i 2000 caratteri.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $expirationDate = $this->input('expiration_date');
            $revisionDate = $this->input('revision_date');

            if (!$expirationDate || !$revisionDate) {
                return;
            }

            if ($expirationDate && $revisionDate && Carbon::parse($expirationDate)->lt(Carbon::parse($revisionDate))) {
                $validator->errors()->add('expiration_date', 'La data di scadenza deve essere successiva o uguale alla data di revisione.');
            }
        });
    }
}
