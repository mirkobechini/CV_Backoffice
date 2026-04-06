<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:255', Rule::unique('equipment_types', 'name')->ignore($this->route('equipmentType')->id)],
            'first_inspection_months' => 'nullable|integer|min:0',
            'regular_inspection_months' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il campo nome è obbligatorio.',
            'name.string' => 'Il campo nome deve essere una stringa.',
            'name.max' => 'Il campo nome non può superare i 255 caratteri.',
            'name.unique' => 'Esiste già un tipo di equipaggiamento con questo nome.',
            'first_inspection_months.integer' => 'Il campo mesi per la prima ispezione deve essere un numero intero.',
            'first_inspection_months.min' => 'Il campo mesi per la prima ispezione deve essere almeno 0.',
            'regular_inspection_months.integer' => 'Il campo mesi per le ispezioni regolari deve essere un numero intero.',
            'regular_inspection_months.min' => 'Il campo mesi per le ispezioni regolari deve essere almeno 0.',
        ];
    }
}
