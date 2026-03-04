<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssueRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'description' => 'required|string',
            'event_date' => 'required|date',
            'status' => 'required|in:open,in_progress,closed',
            'image' => 'nullable|image|max:2048', // Optional image upload
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'description.required' => 'La descrizione è obbligatoria.',
            'description.string' => 'La descrizione deve essere una stringa.',
            'event_date.required' => 'La data di segnalazione è obbligatoria.',
            'event_date.date' => 'La data di segnalazione deve essere una data valida.',
            'status.required' => 'Lo stato è obbligatorio.',
            'status.in' => 'Lo stato selezionato non è valido.',
            'image.image' => 'Il file caricato deve essere un\'immagine.',
            'image.max' => 'L\'immagine non può superare i 2MB.',
        ];
    }
}
