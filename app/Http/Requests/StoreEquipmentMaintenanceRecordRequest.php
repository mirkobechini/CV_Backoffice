<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEquipmentMaintenanceRecordRequest extends FormRequest
{
    use AdminOnlyAccess;

    public function rules(): array
    {
        return [
            'provider_id' => 'required|exists:providers,id',
            'appointment_date' => 'required|date',
            'return_date' => 'nullable|date|after_or_equal:appointment_date',
            'activity_type' => ['nullable', 'string', 'max:255', Rule::in(EquipmentMaintenanceRecord::ACTIVITY_TYPES)],
            'notes' => 'nullable|string|max:2000',
            // A differenza di un appuntamento veicolo (sempre un solo
            // veicolo), un appuntamento attrezzature può coinvolgerne più
            // di una insieme (es. collaudo di più estintori dallo stesso
            // fornitore nello stesso giorno).
            'equipment_ids' => 'required|array|min:1',
            'equipment_ids.*' => 'exists:equipment,id',
            'issue_ids' => 'nullable|array',
            'issue_ids.*' => 'exists:equipment_issues,id',
            'completed_issue_ids' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'provider_id.required' => 'Il campo fornitore è obbligatorio.',
            'provider_id.exists' => 'Il fornitore selezionato non esiste.',
            'appointment_date.required' => 'Il campo data appuntamento è obbligatorio.',
            'appointment_date.date' => 'Il campo data appuntamento deve essere una data valida.',
            'return_date.date' => 'Il campo data rientro deve essere una data valida.',
            'return_date.after_or_equal' => 'La data di rientro deve essere uguale o successiva alla data di appuntamento.',
            'activity_type.in' => 'La tipologia attività selezionata non è valida.',
            'notes.max' => 'Le note non possono superare i 2000 caratteri.',
            'equipment_ids.required' => 'Seleziona almeno un\'attrezzatura.',
            'equipment_ids.min' => 'Seleziona almeno un\'attrezzatura.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $equipmentIds = $this->input('equipment_ids', []);

            // Stessa logica permissiva dell'elenco attrezzature: quelle non
            // assegnate a un veicolo restano selezionabili da chiunque,
            // solo quelle assegnate vengono verificate sul gruppo.
            if (! empty($equipmentIds)) {
                $invalid = Equipment::whereIn('id', $equipmentIds)
                    ->whereNotNull('vehicle_id')
                    ->whereDoesntHave('vehicle', fn ($q) => $q->forCurrentUser())
                    ->exists();

                if ($invalid) {
                    $validator->errors()->add('equipment_ids', 'Una o più attrezzature selezionate non esistono o non appartengono al tuo gruppo.');
                }
            }

            // I guasti collegati devono appartenere a una delle attrezzature
            // selezionate: senza questo controllo si potrebbe collegare
            // l'appuntamento a un guasto di un'attrezzatura completamente
            // estranea alla selezione.
            $issueIds = $this->input('issue_ids', []);
            if (! empty($issueIds)) {
                $invalidIssue = EquipmentIssue::whereIn('id', $issueIds)
                    ->whereNotIn('equipment_id', $equipmentIds)
                    ->exists();

                if ($invalidIssue) {
                    $validator->errors()->add('issue_ids', 'Uno o più guasti selezionati non appartengono alle attrezzature scelte.');
                }
            }
        });
    }
}
