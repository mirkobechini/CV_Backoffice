<?php

namespace App\Services\CsvImport;

use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Vehicle;
use Carbon\Carbon;

class IssueCsvImporter extends AbstractCsvImporter
{
    /**
     * Valida righe guasti — supporta formato Excel-like con header variabili.
     */
    public function validate(array $rows, array $options = []): array
    {
        $results = [];
        $vehiclesCache = [];

        // Se il veicolo è passato come parametro (es. dal nome file "1727 - Guasti.csv")
        $vehicleRef = $options['vehicle_ref'] ?? null;
        if ($vehicleRef) {
            $vehicleRef = trim($vehicleRef);
        }

        foreach ($rows as $index => $row) {
            $result = [
                'row' => $index + 2,
                'data' => $row,
                'valid' => true,
                'warnings' => [],
                'errors' => [],
            ];

            $values = array_values($row);

            // Cerca descrizione: prima per nome colonna, poi per posizione (colonna _col_1)
            $description = $row['DESCRIZIONE'] ?? $row['descrizione'] ?? $row['Descrizione'] ?? '';
            if (empty($description)) {
                $description = $row['_col_0'] ?? $values[1] ?? '';
            }

            // Non required
            $result['data']['_description'] = $description;
            if (empty($description)) {
                $result['warnings'][] = 'Descrizione vuota.';
            }

            // Veicolo: usa il parametro se fornito, altrimenti cerca nel file
            if ($vehicleRef) {
                if (!isset($vehiclesCache[$vehicleRef])) {
                    // forCurrentUser(): senza, un capo poteva importare dati
                    // su un veicolo di un altro gruppo indovinandone sigla/targa.
                    $vehiclesCache[$vehicleRef] = Vehicle::where(fn ($q) => $q->where('internal_code', $vehicleRef)
                        ->orWhere('license_plate', $vehicleRef))
                        ->forCurrentUser()
                        ->first();
                }
                $vehicle = $vehiclesCache[$vehicleRef];
                if (!$vehicle) {
                    $result['valid'] = false;
                    $result['errors'][] = "Veicolo \"{$vehicleRef}\" non trovato.";
                } else {
                    $result['data']['_vehicle_id'] = $vehicle->id;
                    $result['data']['_vehicle_label'] = $vehicle->internal_code . ' - ' . $vehicle->license_plate;
                }
            } else {
                $result['valid'] = false;
                $result['errors'][] = 'Nessun veicolo associato. Specifica la sigla nel nome file (es. "1727 - Guasti.csv").';
            }

            // Data — cerca in tutti i formati possibili
            $rawDate = $row['data'] ?? $row['DATA'] ?? $row['Data'] ?? $row['Data evento'] ?? $row['event_date'] ?? '';
            // Verifica che non sia l'header stesso della colonna
            if (in_array(mb_strtolower(trim($rawDate)), ['data', 'data evento', 'event_date', 'date', 'giorno', 'mese', 'periodo'], true)) {
                $rawDate = '';
            }
            if (empty($rawDate)) {
                $values = array_values($row);
                $rawDate = $values[0] ?? '';
                if (in_array(mb_strtolower(trim($rawDate)), ['data', 'data evento', 'date', 'giorno', 'mese', 'periodo'], true)) {
                    $rawDate = '';
                }
            }

            if (empty($rawDate)) {
                $result['data']['_date'] = Carbon::today()->toDateString();
                $result['warnings'][] = 'Data non specificata, usata data odierna.';
            } else {
                $parsed = $this->parseDate($rawDate);
                if ($parsed) {
                    $result['data']['_date'] = $parsed->toDateString();
                } else {
                    $result['warnings'][] = "Data \"{$rawDate}\" non riconosciuta, usata data odierna.";
                    $result['data']['_date'] = Carbon::today()->toDateString();
                }
            }

            // Stato (RISOLTO colonna)
            $risolto = $row['RISOLTO'] ?? $row['risolto'] ?? '';
            if (
                in_array(mb_strtolower(trim($risolto)), ['ok', 'x', 'si', 'cambiate', 'funziona (andava ricaricata)', 'ok']) ||
                stripos($risolto, 'ok') !== false
            ) {
                $result['data']['_status'] = 'closed';
            } else {
                $result['data']['_status'] = 'open';
            }

            $results[] = $result;
        }

        return $results;
    }

    public function import(array $row): array
    {
        if (!isset($row['_vehicle_id']) || !$this->vehicleBelongsToCurrentUser($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        $vehicleId = $row['_vehicle_id'];
        $description = $row['_description'] ?? '';
        $status = $row['_status'] ?? 'open';
        $eventDate = $row['_date'] ?? Carbon::today()->toDateString();
        $appointmentDate = $row['_appointment_date'] ?? '';
        $providerName = $row['_provider_name'] ?? '';

        // Se c'è una data appuntamento, risolviamo subito data/fornitore e
        // falliamo l'intera riga PRIMA di creare il guasto se manca
        // qualcosa di necessario — maintenance_records.provider_id è
        // NOT NULL (come nel form manuale, dove è obbligatorio): provare a
        // creare l'appuntamento con provider_id null dopo aver già creato
        // il guasto lasciava un guasto orfano e, peggio, l'eccezione di
        // vincolo DB non gestita faceva fallire l'intera transazione di
        // confirm(), perdendo anche le altre righe del batch.
        $parsedAppointmentDate = null;
        $providerId = null;
        if (!empty($appointmentDate)) {
            $parsedAppointmentDate = $this->parseDate($appointmentDate);
            if (!$parsedAppointmentDate) {
                return ['error' => 'Data appuntamento "' . $appointmentDate . '" non valida.'];
            }

            if (!empty($providerName)) {
                $provider = Provider::where('name', 'like', '%' . $providerName . '%')->first();
                if ($provider) {
                    $providerId = $provider->id;
                }
            }

            if ($providerId === null) {
                return ['error' => 'Fornitore "' . $providerName . '" non trovato: impossibile creare l\'appuntamento per "' . mb_substr($description, 0, 50) . '".'];
            }
        }

        // Controllo se esiste già un guasto simile. whereDate() invece di
        // where(): un confronto di uguaglianza esatta su stringa falliva
        // sempre se la colonna conteneva un suffisso orario, non rilevando
        // mai il duplicato.
        $exists = Issue::where('vehicle_id', $vehicleId)
            ->where('description', $description)
            ->whereDate('event_date', $eventDate)
            ->exists();

        if ($exists) {
            return ['error' => 'Guasto già esistente: "' . mb_substr($description, 0, 50) . '"'];
        }

        // Crea il guasto
        $issue = Issue::create([
            'vehicle_id' => $vehicleId,
            'description' => $description,
            'status' => $status,
            'event_date' => $eventDate,
        ]);

        // Se c'è data appuntamento (già validata sopra), crea anche l'appuntamento
        if ($parsedAppointmentDate) {
            $maintenanceRecord = MaintenanceRecord::create([
                'vehicle_id' => $vehicleId,
                'provider_id' => $providerId,
                'appointment_date' => $parsedAppointmentDate->toDateString(),
                'activity_type' => 'Riparazione',
            ]);

            // Collega il guasto all'appuntamento con lo stesso pattern
            // usato ovunque altrove nell'app (itemable polimorfico su
            // MaintenanceRecordItem).
            $maintenanceRecord->items()->create([
                'itemable_id' => $issue->id,
                'itemable_type' => Issue::class,
            ]);
        }

        return ['success' => true];
    }

    /**
     * Parsa una data in vari formati possibili.
     * Per date GG/MM senza anno: se il mese è già passato (<= mese corrente) usa anno corrente,
     * altrimenti usa anno precedente (es. 12/8 → 2026, 15/10 → 2025).
     */
    private function parseDate(string $date): ?Carbon
    {
        $date = trim($date);

        // AAAA-MM-GG
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return Carbon::parse($date);
            } catch (\Exception $e) {
                return null;
            }
        }

        // GG/MM/AAAA
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $date);
            } catch (\Exception $e) {
                return null;
            }
        }

        // GG/MM (senza anno) — supporta anche G/M senza zero padding
        if (preg_match('/^\d{1,2}\/\d{1,2}$/', $date)) {
            try {
                $parsed = Carbon::createFromFormat('d/m', $date);
                $today = Carbon::today();
                // Se mese <= mese corrente, usa anno corrente, altrimenti anno precedente
                $year = $parsed->month <= $today->month ? $today->year : $today->year - 1;
                return $parsed->setYear($year);
            } catch (\Exception $e) {
                return null;
            }
        }

        // GG/MM/AA
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{1,2}$/', $date)) {
            try {
                return Carbon::createFromFormat('d/m/y', $date);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
