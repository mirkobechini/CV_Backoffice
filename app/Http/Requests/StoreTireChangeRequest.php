<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreTireChangeRequest extends FormRequest
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
            'changed_date' => 'required|date',
            'mileage_at_change' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'changed_date.required' => 'La data del cambio è obbligatoria.',
            'changed_date.date' => 'La data del cambio deve essere una data valida.',
            'mileage_at_change.integer' => 'Il chilometraggio deve essere un numero intero.',
        ];
    }
}
