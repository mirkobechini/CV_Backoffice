<?php

namespace App\Services;

use App\Exceptions\VehicleScanException;
use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Estrae i dati di un veicolo da una foto del libretto di circolazione
 * tramite un LLM vision, per pre-compilare il form di creazione (l'utente
 * verifica sempre prima di salvare, vedi VehicleController::scanRegistrationCard()).
 *
 * Usa il formato chat-completions compatibile OpenAI: vale sia per
 * OpenRouter (provider di default) sia per la maggior parte degli altri
 * provider, quindi cambiare provider/modello è solo questione delle chiavi
 * in config('services.openrouter'), senza toccare questo codice.
 */
class VehicleScanService
{
    /**
     * Campi attesi nella risposta JSON del modello. "brand"/"car_model" sono
     * testo libero letto dal documento: la corrispondenza con Brand/CarModel
     * esistenti avviene dopo, in matchBrandAndModel().
     */
    private const EXPECTED_FIELDS = [
        'license_plate',
        'immatricolation_date',
        'fuel_type',
        'brand',
        'car_model',
        'vin',
        'color',
        'seats',
        'environmental_class',
        'max_mass_kg',
        'engine_displacement_cc',
        'engine_power_kw',
        'vehicle_category',
        'allowed_tire_size',
        'has_timing_belt_suggested',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
Sei un assistente che legge libretti di circolazione italiani (carta di
circolazione) da due foto, fronte e retro, e ne estrae i dati in JSON.
Il documento usa codici standard: A=targa, B=data di immatricolazione, D.1=marca,
D.3=denominazione commerciale (modello), E=numero di telaio (VIN),
F.2=massa massima ammissibile in kg, P.1=cilindrata in cc,
P.2=potenza massima netta in kW, P.3=alimentazione, R=colore,
S.1=numero posti a sedere, V.9=classe ambientale (es. "Euro 6"),
J=categoria del veicolo (es. "M1"). La misura dei pneumatici, se presente,
va restituita nel formato canonico "LARGHEZZA/PROFILOR DIAMETROC" (es.
"225/75R16C"), senza spazi prima di "R", con "C" solo se rinforzato.

Rispondi SOLO con un oggetto JSON con queste chiavi (usa null se un dato
non è leggibile o non è presente sul documento):
{
  "license_plate": string|null,
  "immatricolation_date": string|null (formato YYYY-MM-DD),
  "fuel_type": "benzina"|"diesel"|"elettrico"|"ibrido"|null,
  "brand": string|null (testo così come scritto, campo D.1),
  "car_model": string|null (testo così come scritto, campo D.3),
  "vin": string|null,
  "color": string|null,
  "seats": integer|null,
  "environmental_class": string|null,
  "max_mass_kg": integer|null,
  "engine_displacement_cc": integer|null,
  "engine_power_kw": integer|null,
  "vehicle_category": string|null,
  "allowed_tire_size": string|null,
  "has_timing_belt_suggested": boolean|null
}

"has_timing_belt_suggested" NON è leggibile sul documento: è una tua stima,
basata sulla conoscenza generale del motore di quella marca/modello/
cilindrata, sul fatto che tipicamente monti una cinghia (true) o una
catena (false) di distribuzione. Restituisci null se non hai un'indicazione
ragionevole. Questo campo è meno affidabile degli altri e verrà mostrato
all'utente come suggerimento da verificare, non come dato certo.
PROMPT;

    public function __construct(
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?string $baseUrl = null,
    ) {
        $this->apiKey ??= config('services.openrouter.key');
        $this->model ??= config('services.openrouter.model');
        $this->baseUrl ??= config('services.openrouter.base_url');
    }

    /**
     * @param  string[]  $absoluteImagePaths  fronte e retro del libretto: i
     *                                        dati anagrafici sono sul fronte,
     *                                        i timbri di revisione sul retro.
     * @return array<string, mixed> campi come da EXPECTED_FIELDS, mancanti = null.
     *
     * @throws VehicleScanException
     */
    public function scan(array $absoluteImagePaths): array
    {
        if (! $this->apiKey) {
            throw new VehicleScanException('Nessuna chiave API configurata per la scansione del libretto.');
        }

        $content = [
            ['type' => 'text', 'text' => 'Estrai i dati da queste foto del libretto di circolazione (fronte e retro).'],
        ];

        foreach ($absoluteImagePaths as $imagePath) {
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($imagePath));
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"]];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if (! $response->successful()) {
            throw new VehicleScanException('Chiamata al servizio di scansione fallita: '.$response->status());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new VehicleScanException('Risposta del servizio di scansione non valida.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new VehicleScanException('Impossibile leggere i dati estratti dal libretto.');
        }

        $result = [];
        foreach (self::EXPECTED_FIELDS as $field) {
            $result[$field] = $decoded[$field] ?? null;
        }

        return $result;
    }

    /**
     * Risolve testo marca/modello letto dal documento in id Brand/CarModel
     * esistenti. Nessun matching esiste altrove nel codebase: qui ci si
     * ferma a un confronto esatto case-insensitive e, in fallback, a un
     * "contiene" — se nessuno dei due trova corrispondenza, entrambi
     * restano null e l'utente sceglie a mano (nessuna creazione automatica
     * di nuovi Brand/CarModel, per evitare doppioni da errori di lettura).
     *
     * @return array{brand_id: ?int, car_model_id: ?int}
     */
    public function matchBrandAndModel(?string $brandText, ?string $carModelText): array
    {
        $brand = $this->findByName(Brand::query(), $brandText);

        if (! $brand) {
            return ['brand_id' => null, 'car_model_id' => null];
        }

        $carModel = $this->findByName(CarModel::query()->where('brand_id', $brand->id), $carModelText);

        return [
            'brand_id' => $brand->id,
            'car_model_id' => $carModel?->id,
        ];
    }

    private function findByName(Builder $query, ?string $text)
    {
        $text = $text ? trim($text) : null;

        if (! $text) {
            return null;
        }

        $exact = (clone $query)->whereRaw('LOWER(name) = ?', [Str::lower($text)])->first();

        if ($exact) {
            return $exact;
        }

        return (clone $query)->where('name', 'like', '%'.$text.'%')->first();
    }
}
