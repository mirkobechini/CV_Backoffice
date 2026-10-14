<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Issue;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueRequest extends FormRequest
{
    use AdminOnlyAccess;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
            'tire_ids' => ['nullable', 'array'],
            'tire_ids.*' => [new BelongsToCurrentUserGroup(Tire::class, message: 'Uno o più pneumatici selezionati non esistono o non appartengono al tuo gruppo.')],
            'part' => ['required', 'in:'.implode(',', Issue::PARTS)],
            'description' => 'required|string',
            'notes' => 'nullable|string',
            'event_date' => 'required|date',
            'status' => 'required|in:open,in_progress,closed',
            // La regola "image" (basata sul MIME rilevato) rifiuta HEIC/HEIF,
            // il formato di default delle foto su iPhone: da mobile il
            // caricamento falliva sempre per quelle. "mimes" verifica invece
            // l'estensione, quindi possiamo includerle esplicitamente.
            // SVG esclusa di proposito: vedi commento in StoreIssueRequest.
            'image' => 'nullable|mimes:jpeg,png,jpg,gif,bmp,webp,heic,heif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Il veicolo è obbligatorio.',
            'part.required' => 'La parte del veicolo coinvolta è obbligatoria.',
            'part.in' => 'La parte selezionata non è valida.',
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
