<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\FleetDevice;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFleetDeviceRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', [
                FleetDevice::TYPE_TELEPASS,
                FleetDevice::TYPE_UNIPOL_MOVE,
                FleetDevice::TYPE_ALTRO,
            ])],
            'code' => ['required', 'string', 'max:255'],
            'vehicle_id' => ['nullable', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'provider_notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Il campo tipo è obbligatorio.',
            'type.in' => 'Tipo di dispositivo non valido.',
            'code.required' => 'Il campo codice è obbligatorio.',
        ];
    }
}
