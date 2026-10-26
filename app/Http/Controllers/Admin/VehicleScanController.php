<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VehicleScanException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanVehicleRegistrationCardRequest;
use App\Models\Vehicle;
use App\Services\VehicleRegistrationCardService;
use App\Services\VehicleScanService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scansione del libretto di circolazione tramite LLM, per precompilare il
 * form di creazione/modifica veicolo. Estratto da VehicleController per
 * tenerlo sotto le 400 righe (audit 2026-10-22, Q1).
 */
class VehicleScanController extends Controller
{
    public function __construct(
        private readonly VehicleRegistrationCardService $registrationCardService,
    ) {}

    /**
     * Scansiona il fronte del libretto di circolazione (contiene tutti i
     * dati anagrafici letti) tramite VehicleScanService e reindirizza al
     * form di creazione (o, se scansionato dalla pagina di modifica di un
     * veicolo esistente, al form di modifica di quel veicolo) già
     * precompilato (withInput, lo stesso meccanismo usato da una
     * validazione fallita) perché l'utente verifichi/corregga prima di
     * salvare — nessun veicolo viene creato/modificato qui.
     *
     * Viene salvato subito in una posizione "pending" prima ancora di
     * chiamare l'LLM, così resta disponibile (e riutilizzabile come carta
     * di circolazione definitiva in store()/update()) anche se l'estrazione
     * fallisce. La lettura per la scansione vera e propria avviene invece
     * dal file temporaneo dell'upload (valido solo per la durata di questa
     * richiesta): funziona indipendentemente dal disco di storage
     * configurato (locale in sviluppo, S3/R2 in produzione — vedi
     * config('filesystems.uploads_disk')), che per un disco remoto non
     * espone un percorso locale leggibile direttamente.
     */
    public function scanRegistrationCard(ScanVehicleRegistrationCardRequest $request, VehicleScanService $vehicleScanService)
    {
        $vehicle = $request->filled('vehicle_id')
            ? Vehicle::forCurrentUser()->findOrFail($request->input('vehicle_id'))
            : null;

        $this->authorize($vehicle ? 'update' : 'create', $vehicle ?? Vehicle::class);

        $targetRoute = $vehicle
            ? route('admin.vehicles.edit', $vehicle)
            : route('admin.vehicles.create');

        $frontPhoto = $request->file('photo_front');
        $frontTempPath = $frontPhoto->getRealPath();

        $frontFileName = Str::random(40).'.'.$frontPhoto->getClientOriginalExtension();
        $frontPendingPath = $frontPhoto->storeAs('registration_cards/pending', $frontFileName, $this->registrationCardService->disk());

        try {
            $extracted = $vehicleScanService->scan([$frontTempPath]);
        } catch (VehicleScanException $e) {
            // Il messaggio mostrato all'utente resta generico (non è un
            // dettaglio su cui può agire), ma senza questo log la causa
            // reale (chiave API mancante, chiamata fallita, risposta non
            // valida...) restava impossibile da scoprire dopo il fatto.
            Log::warning("Scansione libretto fallita: {$e->getMessage()}", [
                'vehicle_id' => $vehicle?->id,
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->to($targetRoute)
                ->with('error', 'Impossibile leggere il libretto: inserisci i dati manualmente.')
                ->with('scanned_registration_card_path', $frontPendingPath);
        }

        $matched = $vehicleScanService->matchBrandAndModel($extracted['brand'], $extracted['car_model']);

        $prefill = array_filter([
            'license_plate' => $extracted['license_plate'],
            'immatricolation_date' => $extracted['immatricolation_date'],
            'fuel_type' => $extracted['fuel_type'],
            'brand_id' => $matched['brand_id'],
            'car_model_id' => $matched['car_model_id'],
            'vin' => $extracted['vin'],
            'color' => $extracted['color'],
            'seats' => $extracted['seats'],
            'environmental_class' => $extracted['environmental_class'],
            'max_mass_kg' => $extracted['max_mass_kg'],
            'engine_displacement_cc' => $extracted['engine_displacement_cc'],
            'engine_power_kw' => $extracted['engine_power_kw'],
            'vehicle_category' => $extracted['vehicle_category'],
            'allowed_tire_sizes' => empty($extracted['allowed_tire_sizes']) ? null : $extracted['allowed_tire_sizes'],
            // Il libretto non distingue cinghia a secco/bagno d'olio (va
            // verificata fisicamente): "a secco" è la stima più prudente,
            // l'utente la corregge se necessario prima di salvare.
            'timing_belt_type' => $extracted['has_timing_belt_suggested'] === null
                ? null
                : ($extracted['has_timing_belt_suggested'] ? Vehicle::TIMING_BELT_TYPE_DRY_BELT : Vehicle::TIMING_BELT_TYPE_CHAIN),
        ], fn ($value) => $value !== null);

        $redirect = redirect()->to($targetRoute)
            ->withInput($prefill)
            ->with('scanned_registration_card_path', $frontPendingPath)
            ->with('status', 'Dati importati dal libretto: verifica prima di salvare.');

        if ($extracted['has_timing_belt_suggested'] !== null) {
            $redirect->with('timing_belt_suggested', true);
        }

        if (! $matched['brand_id'] && $extracted['brand']) {
            $redirect->with('unmatched_brand_text', $extracted['brand']);
        }

        if (! $matched['car_model_id'] && $extracted['car_model']) {
            $redirect->with('unmatched_model_text', $extracted['car_model']);
        }

        return $redirect;
    }
}
