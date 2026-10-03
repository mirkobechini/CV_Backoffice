<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentIssueRequest extends FormRequest
{
    use AdminOnlyAccess;

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id', function ($attribute, $value, $fail) {
                $equipment = Equipment::find($value);
                if (! $equipment?->vehicle_id) {
                    return;
                }
                $belongs = Equipment::whereKey($value)->whereHas('vehicle', fn ($q) => $q->forCurrentUser())->exists();
                if (! $belongs) {
                    $fail('L\'attrezzatura selezionata non esiste o non appartiene al tuo gruppo.');
                }
            }],
            'description' => 'required|string',
            'event_date' => 'required|date',
            'status' => 'required|in:open,in_progress,closed',
        ];
    }

    public function messages(): array
    {
        return [
            'equipment_id.required' => 'L\'attrezzatura è obbligatoria.',
            'equipment_id.exists' => 'L\'attrezzatura selezionata non esiste.',
            'description.required' => 'La descrizione è obbligatoria.',
            'description.string' => 'La descrizione deve essere una stringa.',
            'event_date.required' => 'La data di segnalazione è obbligatoria.',
            'event_date.date' => 'La data di segnalazione deve essere una data valida.',
            'status.required' => 'Lo stato è obbligatorio.',
            'status.in' => 'Lo stato selezionato non è valido.',
        ];
    }
}
