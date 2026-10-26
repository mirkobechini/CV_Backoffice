<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Identica a StoreEquipmentRequest tranne per "serial_number", che deve
 * ignorare il record corrente nel controllo di unicità (audit 2026-10-22,
 * Q6): prima duplicata per intero invece di estendere.
 */
class UpdateEquipmentRequest extends StoreEquipmentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'serial_number' => 'unique:equipment,serial_number,'.$this->route('equipment')?->id.',id|nullable|string|max:255',
        ]);
    }
}
