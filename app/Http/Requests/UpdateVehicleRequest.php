<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Identica a StoreVehicleRequest tranne per "license_plate"/"internal_code",
 * che devono ignorare il record corrente nel controllo di unicità (audit
 * 2026-10-22, Q6): prima duplicata per intero invece di estendere.
 */
class UpdateVehicleRequest extends StoreVehicleRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'license_plate' => ['required', 'string', 'size:7', 'regex:/^[A-Z]{2}[0-9]{3}[A-Z]{2}$/', Rule::unique('vehicles', 'license_plate')->ignore($this->route('vehicle')->id)],
            'internal_code' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/', Rule::unique('vehicles', 'internal_code')->ignore($this->route('vehicle')->id)],
        ]);
    }
}
