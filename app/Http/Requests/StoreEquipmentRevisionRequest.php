<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentRevisionRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => 'required|in:revision,collaudo',
            'performed_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Il tipo di controllo è obbligatorio.',
            'kind.in' => 'Il tipo di controllo selezionato non è valido.',
            'performed_date.required' => 'La data è obbligatoria.',
            'performed_date.date' => 'La data deve essere una data valida.',
            'notes.max' => 'Le note non possono superare i 2000 caratteri.',
        ];
    }
}
