<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:20|unique:discount_codes,code',
            'percent' => 'required|integer|min:1|max:100',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Esiste già un codice sconto con questo codice.',
            'percent.required' => 'La percentuale di sconto è obbligatoria.',
            'percent.min' => 'La percentuale deve essere almeno 1.',
            'percent.max' => 'La percentuale non può superare 100.',
            'expires_at.after' => 'La data di scadenza deve essere futura.',
        ];
    }
}
