<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Identica a StoreFleetDeviceRequest tranne per l'assenza di vehicle_id
 * (audit 2026-10-22, Q6): il veicolo si cambia solo tramite
 * FleetDeviceController::reassign(), che registra anche lo storico. Un
 * update() che lo tocchi silenziosamente lascerebbe una riassegnazione
 * senza traccia in fleet_device_assignments.
 */
class UpdateFleetDeviceRequest extends StoreFleetDeviceRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['vehicle_id']);
    }
}
