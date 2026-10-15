<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class StoreSuperadminGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'capo_name' => ['required', 'string', 'max:255'],
            'capo_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'capo_password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "Il nome dell'associazione è obbligatorio.",
            'capo_name.required' => 'Il nome del capo è obbligatorio.',
            'capo_email.required' => "L'email del capo è obbligatoria.",
            'capo_email.unique' => 'Esiste già un utente con questa email.',
            'capo_password.required' => 'La password è obbligatoria.',
        ];
    }
}
