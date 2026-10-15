<?php

namespace App\Http\Requests;

use App\Models\License;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLicenseRequest extends FormRequest
{
    /**
     * Autorizzazione già garantita dal middleware 'superadmin' sull'intero
     * gruppo di rotte /superadmin.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier' => ['required', Rule::in(License::TIERS)],
            'max_vehicles' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => [Rule::in([License::FEATURE_STATISTICS, License::FEATURE_AI_BACKFILL])],
            'list_price_eur' => 'nullable|numeric|min:0',
            'discount_code' => 'nullable|string|max:20',
            'starts_at' => 'required|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'tier.required' => 'Il tier è obbligatorio.',
            'tier.in' => 'Il tier selezionato non è valido.',
            'max_vehicles.integer' => 'Il limite veicoli deve essere un numero intero.',
            'max_users.integer' => 'Il limite utenti deve essere un numero intero.',
            'starts_at.required' => 'La data di inizio è obbligatoria.',
            'expires_at.after_or_equal' => 'La data di scadenza non può precedere la data di inizio.',
        ];
    }
}
