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
            'completed_issue_ids' => 'nullable|array',
            'completed_issue_ids.*' => 'exists:issues,id',
            'completed_deadline_ids' => 'nullable|array',
            'completed_deadline_ids.*' => 'exists:deadlines,id',
            'provider_id' => 'required|exists:providers,id',
            'appointment_date' => 'required|date',
            'return_date' => 'nullable|date|after_or_equal:appointment_date',
            'activity_type' => ['nullable', 'string', 'max:255', Rule::in(MaintenanceRecord::ACTIVITY_TYPES)],
            'mileage_at_service' => 'nullable|integer|min:0',
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

            if (! empty($issueIds) && $appointmentDate) {
                $issues = Issue::whereIn('id', $issueIds)->get();

                foreach ($issues as $issue) {
                    if ($issue->event_date && Carbon::parse($appointmentDate)->lt(Carbon::parse($issue->event_date))) {
                        $validator->errors()->add('appointment_date', "La data dell'appuntamento non può essere precedente alla data del guasto '{$issue->description}'.");
                    }
                }
            }

            // Se è selezionato un tagliando, il chilometraggio è obbligatorio
            // per calcolare la scadenza km del prossimo tagliando.
            $deadlineIds = $this->input('deadline_ids', []);
            if (! empty($deadlineIds)) {
                $hasTagliando = \App\Models\Deadline::whereIn('id', $deadlineIds)
                    ->where('type', \App\Models\Deadline::TYPE_TAGLIANDO)
                    ->exists();

                if ($hasTagliando && $this->input('mileage_at_service') === null) {
                    $validator->errors()->add('mileage_at_service', 'Il chilometraggio è obbligatorio quando è selezionato un tagliando.');
                }
            }

            // Controllo conflitto: lo stesso veicolo non può avere appuntamenti
            // sovrapposti (date che si intersecano).
            $vehicleId = $this->input('vehicle_id');
            $startDate = $this->input('appointment_date');
            $endDate = $this->input('return_date') ?? $startDate;

            if ($vehicleId && $startDate) {
                $conflict = \App\Models\MaintenanceRecord::where('vehicle_id', $vehicleId)
                    ->where(function ($q) use ($startDate, $endDate) {
                        // Appuntamento esistente che si sovrappone al nuovo intervallo
                        $q->where(function ($q2) use ($startDate, $endDate) {
                            // esistente.appointment_date <= nuovo.return_date
                            // AND esistente.return_date >= nuovo.appointment_date
                            $q2->where('appointment_date', '<=', $endDate)
                                ->where(function ($q3) use ($startDate) {
                                    $q3->whereNull('return_date')
                                        ->orWhere('return_date', '>=', $startDate);
                                });
                        });
                    })
                    ->first();

                if ($conflict) {
                    $conflictEnd = $conflict->return_date?->toDateString() ?? 'in corso';
                    $validator->errors()->add('appointment_date', "Il veicolo è già in officina dal {$conflict->appointment_date?->toDateString()} al {$conflictEnd}. Impossibile inserire un appuntamento sovrapposto.");
                }
            }
        });
    }
}
