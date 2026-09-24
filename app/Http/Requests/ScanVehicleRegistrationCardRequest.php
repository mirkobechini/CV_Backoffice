<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AdminOnlyAccess;
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
            'photo' => 'required|mimes:jpeg,png,jpg,heic,heif|max:8192',
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'La foto del libretto è obbligatoria.',
            'photo.mimes' => 'La foto deve essere in formato JPG, PNG, HEIC o HEIF.',
            'photo.max' => 'La foto non può superare gli 8MB.',
        ];
    }
}
