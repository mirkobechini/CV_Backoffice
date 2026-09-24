<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Http\Requests\Concerns\ValidatesTireSelection;
use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMaintenanceRecordRequest extends FormRequest
{
    use AdminOnlyAccess;
    use ValidatesTireSelection;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'issue_ids' => 'nullable|array',
            'issue_ids.*' => new BelongsToCurrentUserGroup(Issue::class, message: 'Uno o più guasti selezionati non esistono o non appartengono al tuo gruppo.'),
            'deadline_ids' => 'nullable|array',
            'deadline_ids.*' => new BelongsToCurrentUserGroup(Deadline::class, message: 'Una o più scadenze selezionate non esistono o non appartengono al tuo gruppo.'),
            'completed_issue_ids' => 'nullable|array',
            'completed_issue_ids.*' => new BelongsToCurrentUserGroup(Issue::class, message: 'Uno o più guasti selezionati non esistono o non appartengono al tuo gruppo.'),
            'completed_deadline_ids' => 'nullable|array',
            'completed_deadline_ids.*' => new BelongsToCurrentUserGroup(Deadline::class, message: 'Una o più scadenze selezionate non esistono o non appartengono al tuo gruppo.'),
            'provider_id' => 'required|exists:providers,id',
            'appointment_date' => 'required|date',
            'return_date' => 'nullable|date|after_or_equal:appointment_date',
            'activity_type' => ['nullable', 'string', 'max:255', Rule::in(MaintenanceRecord::ACTIVITY_TYPES)],
            'issue_resolved' => 'nullable|boolean',
            'mileage_at_service' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
            'target_tire_ids' => 'nullable|array',
            'target_tire_ids.*' => new BelongsToCurrentUserGroup(Tire::class, message: 'Uno o più pneumatici selezionati non esistono o non appartengono al tuo gruppo.'),
            'new_tire_season' => 'nullable|in:summer,winter,all_season',
            'new_tire_group' => 'nullable|in:single,front_pair,rear_pair,full_set',
            'new_tire_position' => 'nullable|in:front_left,front_right,rear_left,rear_right|required_if:new_tire_group,single',
            'new_tire_brand' => 'nullable|string|max:255',
            'new_tire_model_name' => 'nullable|string|max:255',
            'new_tire_size' => ['nullable', 'string', 'max:255', 'regex:' . Tire::SIZE_REGEX],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'issue_ids.array' => 'Il formato dei guasti non è valido.',
            'deadline_ids.array' => 'Il formato delle scadenze non è valido.',
            'provider_id.required' => 'Il campo fornitore è obbligatorio.',
            'provider_id.exists' => 'Il fornitore selezionato non esiste.',
            'appointment_date.required' => 'Il campo data appuntamento è obbligatorio.',
            'appointment_date.date' => 'Il campo data appuntamento deve essere una data valida.',
            'return_date.date' => 'Il campo data rientro deve essere una data valida.',
            'return_date.after_or_equal' => 'La data di rientro deve essere uguale o successiva alla data di appuntamento.',
            'activity_type.string' => 'Il campo tipologia attività deve essere una stringa.',
            'activity_type.max' => 'Il campo tipologia attività non può superare i 255 caratteri.',
            'activity_type.in' => 'La tipologia attività selezionata non è valida.',
            'new_tire_size.regex' => 'La misura deve essere nel formato standard (es. 225/75R16C).',
            'issue_resolved.boolean' => 'Il valore selezionato per la risoluzione del guasto non è valido.',
            'notes.string' => 'Il campo note deve essere testo.',
            'notes.max' => 'Le note non possono superare i 2000 caratteri.',
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

            // Se è selezionato un tagliando o una cinghia, il chilometraggio è
            // obbligatorio per calcolare la scadenza km del prossimo rinnovo.
            $deadlineIds = $this->input('deadline_ids', []);
            if (! empty($deadlineIds)) {
                $hasKmDeadline = \App\Models\Deadline::whereIn('id', $deadlineIds)
                    ->whereIn('type', [\App\Models\Deadline::TYPE_TAGLIANDO, \App\Models\Deadline::TYPE_CINGHIA])
                    ->exists();

                if ($hasKmDeadline && $this->input('mileage_at_service') === null) {
                    $validator->errors()->add('mileage_at_service', 'Il chilometraggio è obbligatorio quando è selezionato un tagliando o una cinghia.');
                }
            }

            // Controllo conflitto: lo stesso veicolo non può avere appuntamenti
            // sovrapposti (date che si intersecano), escludendo quello corrente.
            $vehicleId = $this->input('vehicle_id');
            $startDate = $this->input('appointment_date');
            $endDate = $this->input('return_date') ?? $startDate;
            $currentId = $this->route('maintenanceRecord')?->id;

            if ($vehicleId && $startDate) {
                $conflict = \App\Models\MaintenanceRecord::where('vehicle_id', $vehicleId)
                    ->where('id', '!=', $currentId)
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where(function ($q2) use ($startDate, $endDate) {
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

            $this->validateTireSelection($validator);
        });
    }
}
