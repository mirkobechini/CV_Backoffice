<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEquipmentRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'revision_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:revision_date',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'name.required' => 'Il campo nome è obbligatorio.',
            'name.string' => 'Il campo nome deve essere una stringa.',
            'name.max' => 'Il campo nome non può superare i 255 caratteri.',
            'serial_number.string' => 'Il campo numero di serie deve essere una stringa.',
            'serial_number.max' => 'Il campo numero di serie non può superare i 255 caratteri.',
            'revision_date.date' => 'Il campo data di revisione deve essere una data valida.',
            'expiration_date.date' => 'Il campo data di scadenza deve essere una data valida.',
            'expiration_date.after_or_equal' => 'Il campo data di scadenza deve essere successivo o uguale alla data di revisione.',
        ];
    }

    public function withValidator(Validator $validator):void
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
