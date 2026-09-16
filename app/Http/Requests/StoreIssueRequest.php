<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreIssueRequest extends FormRequest
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
            'vehicle_id' => 'required|exists:vehicles,id',
            'tire_id' => 'nullable|exists:tires,id',
            'description' => 'required|string',
            'notes' => 'nullable|string',
            'event_date' => 'required|date',
            'status' => 'required|in:open,in_progress,closed',
            // La regola "image" (basata sul MIME rilevato) rifiuta HEIC/HEIF,
            // il formato di default delle foto su iPhone: da mobile il
            // caricamento falliva sempre per quelle. "mimes" verifica invece
            // l'estensione, quindi possiamo includerle esplicitamente.
            'image' => 'nullable|mimes:jpeg,png,jpg,gif,bmp,svg,webp,heic,heif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
            'tire_id.exists' => 'Il set di gomme selezionato non esiste.',
            'description.required' => 'La descrizione è obbligatoria.',
            'description.string' => 'La descrizione deve essere una stringa.',
            'notes.string' => 'Le note devono essere una stringa.',
            'event_date.required' => 'La data di segnalazione è obbligatoria.',
            'event_date.date' => 'La data di segnalazione deve essere una data valida.',
            'status.required' => 'Lo stato è obbligatorio.',
            'status.in' => 'Lo stato selezionato non è valido.',
            'image.image' => 'Il file caricato deve essere un\'immagine.',
            'image.max' => 'L\'immagine non può superare i 2MB.',
        ];
    }
}
