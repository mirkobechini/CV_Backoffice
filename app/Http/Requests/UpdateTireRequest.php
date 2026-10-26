<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Identica a StoreTireRequest tranne per "tire_group" (assente: in modifica
 * si cambia una gomma già esistente in una posizione precisa, non si sceglie
 * quante aggiungerne) e "position", sempre obbligatoria invece che solo per
 * il gruppo "single" (audit 2026-10-22, Q6).
 */
class UpdateTireRequest extends StoreTireRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(Arr::except(parent::rules(), ['tire_group']), [
            'position' => 'required|in:front_left,front_right,rear_left,rear_right',
        ]);
    }

    public function messages(): array
    {
        return array_merge(
            Arr::except(parent::messages(), ['tire_group.required', 'tire_group.in', 'position.required_if']),
            ['position.required' => 'La posizione è obbligatoria.']
        );
    }
}
