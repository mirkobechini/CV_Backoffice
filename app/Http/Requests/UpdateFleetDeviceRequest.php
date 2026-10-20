<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\FleetDevice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFleetDeviceRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * Niente vehicle_id qui: il veicolo si cambia solo tramite
     * FleetDeviceController::reassign(), che registra anche lo storico.
     * Un update() che lo tocchi silenziosamente lascerebbe una
     * riassegnazione senza traccia in fleet_device_assignments.
     *
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
