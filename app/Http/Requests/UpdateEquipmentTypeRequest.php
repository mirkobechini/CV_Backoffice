<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\EquipmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('equipment_types', 'name')->ignore($this->route('equipmentType')->id)],
            'category' => ['required', Rule::in(EquipmentType::CATEGORIES)],
            'first_inspection_months' => 'nullable|integer|min:0',
            'regular_inspection_months' => 'nullable|integer|min:0',
            'collaudo_interval_months' => 'nullable|integer|min:0',
            'max_revisions_before_exchange' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il campo nome è obbligatorio.',
            'name.string' => 'Il campo nome deve essere una stringa.',
            'name.max' => 'Il campo nome non può superare i 255 caratteri.',
            'name.unique' => 'Esiste già un tipo di equipaggiamento con questo nome.',
            'category.required' => 'La categoria è obbligatoria.',
            'category.in' => 'La categoria selezionata non è valida.',
            'first_inspection_months.integer' => 'Il campo mesi per la prima ispezione deve essere un numero intero.',
            'first_inspection_months.min' => 'Il campo mesi per la prima ispezione deve essere almeno 0.',
            'regular_inspection_months.integer' => 'Il campo mesi per le ispezioni regolari deve essere un numero intero.',
            'regular_inspection_months.min' => 'Il campo mesi per le ispezioni regolari deve essere almeno 0.',
            'collaudo_interval_months.integer' => 'Il campo mesi tra un collaudo e l\'altro deve essere un numero intero.',
            'collaudo_interval_months.min' => 'Il campo mesi tra un collaudo e l\'altro deve essere almeno 0.',
            'max_revisions_before_exchange.integer' => 'Il campo numero massimo di revisioni deve essere un numero intero.',
            'max_revisions_before_exchange.min' => 'Il campo numero massimo di revisioni deve essere almeno 1.',
        ];
    }
}
