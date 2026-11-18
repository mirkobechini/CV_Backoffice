<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssueSparePartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.name' => ['required', 'string', 'max:255'],
            'parts.*.notes' => ['nullable', 'string'],
            'parts.*.ordered_at' => ['nullable', 'date'],
            'parts.*.arrived_at' => ['nullable', 'date'],
        ];
    }
}
