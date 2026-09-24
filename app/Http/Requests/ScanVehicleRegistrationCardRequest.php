<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
use App\Models\Vehicle;
use App\Rules\BelongsToCurrentUserGroup;
use Illuminate\Foundation\Http\FormRequest;

class ScanVehicleRegistrationCardRequest extends FormRequest
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
            // "image" (MIME-sniffing) rifiuta HEIC/HEIF (default iPhone);
            // "mimes" controlla invece l'estensione, quindi vanno elencati
            // esplicitamente. SVG escluso di proposito (stesso motivo di
            // StoreIssueRequest::$image): apertura diretta dell'URL salvato
            // eseguirebbe un eventuale <script> incluso — XSS salvato.
            //
            // Servono entrambe le facciate: i dati anagrafici sono sul
            // fronte, i timbri di revisione periodica sul retro.
            'photo_front' => 'required|mimes:jpeg,png,jpg,heic,heif|max:8192',
            'photo_back' => 'required|mimes:jpeg,png,jpg,heic,heif|max:8192',
            // Presente solo quando si scansiona dalla pagina di modifica di
            // un veicolo esistente (vedi VehicleController::scanRegistrationCard()):
            // assente = scansione per la creazione di un nuovo veicolo.
            'vehicle_id' => ['nullable', new BelongsToCurrentUserGroup(Vehicle::class, message: 'Il veicolo selezionato non esiste o non appartiene al tuo gruppo.')],
        ];
    }

    public function messages(): array
    {
        return [
            'photo_front.required' => 'La foto del fronte del libretto è obbligatoria.',
            'photo_front.mimes' => 'La foto del fronte deve essere in formato JPG, PNG, HEIC o HEIF.',
            'photo_front.max' => 'La foto del fronte non può superare gli 8MB.',
            'photo_back.required' => 'La foto del retro del libretto è obbligatoria.',
            'photo_back.mimes' => 'La foto del retro deve essere in formato JPG, PNG, HEIC o HEIF.',
            'photo_back.max' => 'La foto del retro non può superare gli 8MB.',
        ];
    }
}
