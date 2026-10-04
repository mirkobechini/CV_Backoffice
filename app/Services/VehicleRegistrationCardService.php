<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload/sostituzione del file della carta di circolazione per
 * VehicleController::store()/update(), e promozione del file scansionato
 * (posizione "pending") a definitivo. Estratto da VehicleController per
 * tenerlo sotto le 400 righe; consolida anche la logica che store() e
 * update() duplicavano quasi identica.
 */
class VehicleRegistrationCardService
{
    /**
     * Disco per i file caricati dagli utenti: locale in sviluppo, S3/R2 in
     * produzione (vedi config/filesystems.php e la variabile UPLOADS_DISK).
     */
    public function disk(): string
    {
        return config('filesystems.uploads_disk');
    }

    /**
     * Risolve il percorso della carta di circolazione da salvare su
     * $data['registration_card_path'], gestendo sia un upload diretto sia
     * la promozione di un file già scansionato in precedenza (campo
     * scanned_registration_card_path, rimosso da $data in ogni caso: non è
     * una colonna del modello). Se $existingVehicle ha già un file,
     * lo elimina prima di sostituirlo per evitare leak di storage.
     */
    public function resolvePath(Request $request, array &$data, ?Vehicle $existingVehicle = null): void
    {
        $pendingScanPath = $data['scanned_registration_card_path'] ?? null;
        unset($data['scanned_registration_card_path']);

        if ($request->hasFile('registration_card')) {
            $this->deleteExisting($existingVehicle);

            $file = $request->file('registration_card');
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $data['registration_card_path'] = $file->storeAs('registration_cards', $fileName, $this->disk());

            return;
        }

        if ($pendingScanPath && ($promotedPath = $this->promoteScanned($pendingScanPath))) {
            $this->deleteExisting($existingVehicle);
            $data['registration_card_path'] = $promotedPath;
        }
    }

    /**
     * Sposta la foto scansionata (posizione "pending", non ancora legata a
     * nessun veicolo) nella posizione definitiva delle carte di
     * circolazione, così l'utente non deve ricaricarla dopo averla già
     * fornita per la scansione. Verifica che il percorso sia davvero uno
     * "pending" esistente, per non fidarsi ciecamente di un valore POST.
     */
    public function promoteScanned(string $pendingPath): ?string
    {
        if (! Str::startsWith($pendingPath, 'registration_cards/pending/')) {
            return null;
        }

        if (! Storage::disk($this->disk())->exists($pendingPath)) {
            return null;
        }

        $finalPath = 'registration_cards/' . Str::random(40) . '.' . pathinfo($pendingPath, PATHINFO_EXTENSION);
        Storage::disk($this->disk())->move($pendingPath, $finalPath);

        return $finalPath;
    }

    private function deleteExisting(?Vehicle $vehicle): void
    {
        if ($vehicle?->registration_card_path) {
            Storage::disk($this->disk())->delete($vehicle->registration_card_path);
        }
    }
}
