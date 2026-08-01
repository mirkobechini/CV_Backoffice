<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceRecordRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'issue_ids' => 'nullable|array',
            'issue_ids.*' => 'exists:issues,id',
            'deadline_ids' => 'nullable|array',
            'deadline_ids.*' => 'exists:deadlines,id',
            'provider_id' => 'required|exists:providers,id',
            'appointment_date' => 'required|date',
            'return_date' => 'nullable|date|after_or_equal:appointment_date',
            'activity_type' => ['nullable', 'string', 'max:255', Rule::in(MaintenanceRecord::ACTIVITY_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'issue_ids.array' => 'Il formato dei guasti non è valido.',
            'issue_ids.*.exists' => 'Uno o più guasti selezionati non esistono.',
            'deadline_ids.array' => 'Il formato delle scadenze non è valido.',
            'deadline_ids.*.exists' => 'Una o più scadenze selezionate non esistono.',
            'provider_id.required' => 'Il campo fornitore è obbligatorio.',
            'provider_id.exists' => 'Il fornitore selezionato non esiste.',
            'appointment_date.required' => 'Il campo data appuntamento è obbligatorio.',
            'appointment_date.date' => 'Il campo data appuntamento deve essere una data valida.',
            'return_date.date' => 'Il campo data rientro deve essere una data valida.',
            'return_date.after_or_equal' => 'La data di rientro deve essere uguale o successiva alla data di appuntamento.',
            'activity_type.string' => 'Il campo tipologia attività deve essere una stringa.',
            'activity_type.max' => 'Il campo tipologia attività non può superare i 255 caratteri.',
            'activity_type.in' => 'La tipologia attività selezionata non è valida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $issueIds = $this->input('issue_ids', []);
            $appointmentDate = $this->input('appointment_date');

            if (empty($issueIds) || !$appointmentDate) {
                return;
            }

            $issues = Issue::whereIn('id', $issueIds)->get();

            foreach ($issues as $issue) {
                if ($issue->event_date && Carbon::parse($appointmentDate)->lt(Carbon::parse($issue->event_date))) {
                    $validator->errors()->add('appointment_date', "La data dell'appuntamento non può essere precedente alla data del guasto '{$issue->description}'.");
                }
            }
        });
    }
}
