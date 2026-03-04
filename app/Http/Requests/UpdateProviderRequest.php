<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:255',
                'contact_info' => 'nullable|string|max:255',
                'type' => 'required|in:Meccanico,Carrozziere,Gommista,Lavaggio,Allestitore',
            ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'name.string' => 'Il nome deve essere una stringa.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',
            'address.string' => 'L\'indirizzo deve essere una stringa.',
            'address.max' => 'L\'indirizzo non può superare i 255 caratteri.',
            'contact_info.string' => 'Le informazioni di contatto devono essere una stringa.',
            'contact_info.max' => 'Le informazioni di contatto non possono superare i 255 caratteri.',
            'type.required' => 'Il tipo è obbligatorio.',
            'type.in' => 'Il tipo selezionato non è valido. Deve essere uno dei seguenti: Meccanico, Carrozziere, Gommista, Lavaggio, Allestitore.',
        ];
    }
}
