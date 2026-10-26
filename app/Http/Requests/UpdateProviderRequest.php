<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Identica a StoreProviderRequest tranne per "name", che deve ignorare il
 * record corrente nel controllo di unicità (audit 2026-10-22, Q6): prima
 * duplicata per intero invece di estendere.
 */
class UpdateProviderRequest extends StoreProviderRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => 'required|string|max:255|unique:providers,name,'.$this->route('provider')?->id.',id',
        ]);
    }
}
