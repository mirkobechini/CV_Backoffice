<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\MileageLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMileageLogRequest extends FormRequest
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
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'log_date' => ['required', 'date'],
            'mileage' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il campo veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'log_date.required' => 'Il campo data del registro è obbligatorio.',
            'log_date.date' => 'Il campo data del registro deve essere una data valida.',
            'mileage.required' => 'Il campo chilometraggio è obbligatorio.',
            'mileage.integer' => 'Il campo chilometraggio deve essere un numero intero.',
            'mileage.min' => 'Il chilometraggio non può essere negativo.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $vehicleId = $this->input('vehicle_id');
            $newMileage = $this->input('mileage');
            $currentId = $this->route('mileageLog')?->id;

            if (!$vehicleId || !$newMileage) {
                return;
            }

            $lastMileage = MileageLog::where('vehicle_id', $vehicleId)
                ->where('id', '!=', $currentId)
                ->orderByDesc('log_date')
                ->value('mileage');

            if ($lastMileage !== null && (int) $newMileage < (int) $lastMileage) {
                $validator->errors()->add('mileage', 'Il chilometraggio non può essere inferiore all\'ultimo registrato (' . number_format($lastMileage, 0, ',', '.') . ' km).');
            }
        });
    }
}
