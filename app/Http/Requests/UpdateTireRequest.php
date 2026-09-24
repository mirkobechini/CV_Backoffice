<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTireRequest extends FormRequest
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
            'vehicle_id' => ['required', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'season' => 'required|in:summer,winter,all_season',
            'position' => 'required|in:front_left,front_right,rear_left,rear_right',
            'brand' => 'nullable|string|max:255',
            'model_name' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'status' => 'required|in:mounted,stored,retired',
            'mounted_date' => 'nullable|date',
            'mounted_mileage' => 'nullable|integer|min:0',
            'next_change_date' => 'nullable|date',
            'next_change_mileage' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'season.required' => 'La stagionalità è obbligatoria.',
            'season.in' => 'La stagionalità selezionata non è valida.',
            'position.required' => 'La posizione è obbligatoria.',
            'position.in' => 'La posizione selezionata non è valida.',
            'status.required' => 'Lo stato è obbligatorio.',
            'status.in' => 'Lo stato selezionato non è valido.',
            'mounted_date.date' => 'La data di montaggio deve essere una data valida.',
            'mounted_mileage.integer' => 'Il chilometraggio di montaggio deve essere un numero intero.',
            'next_change_date.date' => 'La data del prossimo cambio deve essere una data valida.',
            'next_change_mileage.integer' => 'Il chilometraggio del prossimo cambio deve essere un numero intero.',
        ];
    }
}
