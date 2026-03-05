<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentTypeRequest extends FormRequest
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
            'first_inspection_months' => 'nullable|integer|min:0',
            'regular_inspection_months' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il campo nome è obbligatorio.',
            'first_inspection_months.integer' => 'Il campo mesi per la prima ispezione deve essere un numero intero.',
            'first_inspection_months.min' => 'Il campo mesi per la prima ispezione deve essere almeno 0.',
            'regular_inspection_months.integer' => 'Il campo mesi per le ispezioni regolari deve essere un numero intero.',
            'regular_inspection_months.min' => 'Il campo mesi per le ispezioni regolari deve essere almeno 0.',
        ];
    }
}
