<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\FuelCard;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFuelCardRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', [
                FuelCard::STATUS_ASSIGNED,
                FuelCard::STATUS_JOLLY,
                FuelCard::STATUS_UNASSIGNED,
                FuelCard::STATUS_RETIRED,
            ])],
            'vehicle_id' => ['nullable', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.required' => 'Il campo numero tessera è obbligatorio.',
            'status.required' => 'Il campo stato è obbligatorio.',
            'status.in' => 'Stato non valido.',
        ];
    }

    /**
     * Il veicolo è obbligatorio solo per "assegnata" e vietato per jolly/non
     * assegnata (altrimenti la distinzione tra questi stati collasserebbe
     * su un semplice vehicle_id nullable). "Vecchia (sostituita)" fa
     * eccezione: il veicolo è facoltativo, per mantenere il legame con chi
     * la usava invece di perderlo quando la tessera va in pensione.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $status = $this->input('status');
            $vehicleId = $this->input('vehicle_id');

            if ($status === FuelCard::STATUS_ASSIGNED && ! $vehicleId) {
                $validator->errors()->add('vehicle_id', 'Seleziona il veicolo a cui assegnare la tessera.');
            }

            if (in_array($status, [FuelCard::STATUS_JOLLY, FuelCard::STATUS_UNASSIGNED], true) && $vehicleId) {
                $validator->errors()->add('vehicle_id', 'Una tessera jolly o non assegnata non può avere un veicolo.');
            }
        });
    }
}
