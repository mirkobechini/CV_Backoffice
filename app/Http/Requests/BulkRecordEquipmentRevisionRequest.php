<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkRecordEquipmentRevisionRequest extends FormRequest
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
            'equipment_ids' => 'required|array|min:1',
            'kind' => 'required|in:revision,collaudo',
            'performed_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_ids.required' => 'Seleziona almeno un\'attrezzatura.',
            'equipment_ids.min' => 'Seleziona almeno un\'attrezzatura.',
            'kind.required' => 'Il tipo di controllo è obbligatorio.',
            'kind.in' => 'Il tipo di controllo selezionato non è valido.',
            'performed_date.required' => 'La data è obbligatoria.',
            'performed_date.date' => 'La data deve essere una data valida.',
            'notes.max' => 'Le note non possono superare i 2000 caratteri.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('equipment_ids', []);

            if (empty($ids)) {
                return;
            }

            // L'attrezzatura non assegnata a un veicolo non ha un gruppo
            // proprio: resta selezionabile da chiunque, come nell'indice
            // attrezzature (EquipmentController::index). BelongsToCurrentUserGroup
            // non va bene qui: richiederebbe sempre una relazione vehicle
            // valida, escludendo per errore l'attrezzatura non assegnata.
            $validCount = Equipment::whereIn('id', $ids)
                ->where(function ($q) {
                    $q->whereDoesntHave('vehicle')
                        ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
                })
                ->count();

            if ($validCount !== count($ids)) {
                $validator->errors()->add('equipment_ids', 'Una o più attrezzature selezionate non esistono o non appartengono al tuo gruppo.');
            }
        });
    }
}
