<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Tire;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkUpdateTireRequest extends FormRequest
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
            'tire_ids' => 'required|array|min:1',
            'tire_ids.*' => new BelongsToCurrentUserGroup(Tire::class, message: 'Uno o più pneumatici selezionati non esistono o non appartengono al tuo gruppo.'),
            'brand' => 'nullable|string|max:255',
            'model_name' => 'nullable|string|max:255',
            'size' => ['nullable', 'string', 'max:255', 'regex:' . Tire::SIZE_REGEX],
        ];
    }

    public function messages(): array
    {
        return [
            'tire_ids.required' => 'Seleziona almeno un pneumatico.',
            'tire_ids.min' => 'Seleziona almeno un pneumatico.',
            'size.regex' => 'La misura deve essere nel formato standard (es. 225/75R16C).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('brand') && ! $this->filled('model_name') && ! $this->filled('size')) {
                $validator->errors()->add('brand', 'Compila almeno uno tra marca, modello o misura.');
            }
        });
    }
}
