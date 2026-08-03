<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CsvImportController extends Controller
{
    public function index()
    {
        return view('admin.csv-import.index');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'entity' => 'required|in:issues,mileage-logs',
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'vehicle_ref' => 'nullable|string|max:20',
            'import_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $entity = $request->entity;
        $importYear = $request->import_year ?? (int) date('Y');
        $rows = $this->parseCsv($request->file('csv_file'));

        if (empty($rows)) {
            return back()->with('status_error', 'Il file CSV è vuoto o il formato non è valido.');
        }

        $results = match ($entity) {
            'issues' => $this->validateIssues($rows, $request->vehicle_ref),
            'mileage-logs' => $this->validateMileageLogs($rows, $importYear),
            default => [],
        };

        return view('admin.csv-import.preview', compact('entity', 'results', 'rows'));
    }

    public function confirm(Request $request)
    {
        $entity = $request->entity;
        $editable = $request->input('editable', []);

        if (empty($editable)) {
            return redirect()->route('admin.csv-import.index')
                ->with('status_error', 'Nessun dato da importare.');
        }

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($entity, $editable, &$imported, &$errors) {
            foreach ($editable as $row) {
                // Salta i record esclusi dall'utente
                if (!empty($row['_skip'])) {
                    continue;
                }
                // Solo record validi
                if (empty($row['_valid']) || $row['_valid'] !== '1') {
                    continue;
                }

                $r = match ($entity) {
                    'issues' => $this->importIssue($row),
                    'mileage-logs' => $this->importMileageLog($row),
                    default => ['error' => 'Entità sconosciuta'],
                };
                if (isset($r['error'])) {
                    $errors[] = $r['error'];
                } else {
                    $imported++;
                }
            }
        });

        $message = "{$imported} record importati con successo.";
        if (!empty($errors)) {
            return redirect()->route('admin.csv-import.index')
                ->with('status', $message)
                ->with('status_errors', $errors);
        }

        return redirect()->route($entity === 'issues' ? 'admin.issues.index' : 'admin.mileage-logs.index')
            ->with('status', $message);
    }

    private function parseCsv($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return $rows;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return $rows;
        }

        $headers = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $headers);

        // Assegna nomi posizionali alle colonne senza header (evita sovrascritture)
        $cleanHeaders = [];
        $counter = 0;
        foreach ($headers as $i => $h) {
            if (empty($h)) {
                $cleanHeaders[$i] = '_col_' . $counter++;
            } else {
                $cleanHeaders[$i] = $h;
                $counter = 0;
            }
        }

        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($cleanHeaders as $i => $header) {
                $row[$header] = isset($line[$i]) ? trim($line[$i]) : '';
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Rileva se il CSV è in formato pivot (con colonne mesi).
     */
    private function isPivotFormat(array $headers): bool
    {
        $mesi = [
            'gennaio',
            'febbraio',
            'marzo',
            'aprile',
            'maggio',
            'giugno',
            'luglio',
            'agosto',
            'settembre',
            'ottobre',
            'novembre',
            'dicembre'
        ];
        foreach ($headers as $h) {
            if (in_array(mb_strtolower(trim($h)), $mesi, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converte header mese in numero (1-12).
     */
    private function monthNameToNumber(string $name): ?int
    {
        $map = [
            'gennaio' => 1,
            'febbraio' => 2,
            'marzo' => 3,
            'aprile' => 4,
            'maggio' => 5,
            'giugno' => 6,
            'luglio' => 7,
            'agosto' => 8,
            'settembre' => 9,
            'ottobre' => 10,
            'novembre' => 11,
            'dicembre' => 12,
        ];
        return $map[mb_strtolower(trim($name))] ?? null;
    }

    /**
     * Valida righe di chilometraggi — supporta formato pivot (mese come colonne).
     */
    private function validateMileageLogs(array $rows, int $importYear = 0): array
    {
        if ($importYear === 0) {
            $importYear = (int) date('Y');
        }

        // Determina se è formato pivot
        $sampleHeaders = array_keys($rows[0] ?? []);
        $isPivot = $this->isPivotFormat($sampleHeaders);

        if ($isPivot) {
            return $this->validateMileageLogsPivot($rows, $importYear);
        }

        // Formato semplice (veicolo, mese, km)
        return $this->validateMileageLogsSimple($rows);
    }

    /**
     * Formato pivot: righe=veicoli, colonne=mesi
     * Header: SIGLA, MEZZI, TARGA, GENNAIO, FEBBRAIO, ...
     */
    private function validateMileageLogsPivot(array $rows, int $year): array
    {
        $results = [];
        $vehiclesCache = [];

        // Identifica colonne mesi
        $headers = array_keys($rows[0] ?? []);
        $monthColumns = [];
        foreach ($headers as $h) {
            $monthNum = $this->monthNameToNumber($h);
            if ($monthNum !== null) {
                $monthColumns[$h] = $monthNum;
            }
        }

        foreach ($rows as $index => $row) {
            $sigla = $row['SIGLA'] ?? $row['sigla'] ?? '';
            $targa = $row['TARGA'] ?? $row['targa'] ?? '';

            $vehicleRef = $sigla ?: $targa;
            if (empty($vehicleRef)) {
                continue;
            }

            // Trova veicolo
            if (!isset($vehiclesCache[$vehicleRef])) {
                $vehiclesCache[$vehicleRef] = Vehicle::where('internal_code', $vehicleRef)
                    ->orWhere('license_plate', $vehicleRef)
                    ->first();
            }
            $vehicle = $vehiclesCache[$vehicleRef];

            if (!$vehicle) {
                // Veicolo non trovato, crea record con errore
                $results[] = [
                    'row' => $index + 2,
                    'data' => $row,
                    'valid' => false,
                    'errors' => ["Veicolo \"{$vehicleRef}\" (sigla: {$sigla}, targa: {$targa}) non trovato nel database."],
                    'warnings' => [],
                ];
                continue;
            }

            // Per ogni mese, crea un record
            foreach ($monthColumns as $colName => $monthNum) {
                $kmValue = $row[$colName] ?? '';
                if ($kmValue === '' || $kmValue === null) {
                    continue;
                }

                $kmValue = str_replace(['.', ','], '', $kmValue);
                if (!is_numeric($kmValue)) {
                    continue;
                }

                $kmValue = (int) $kmValue;
                $dateStr = sprintf('%04d-%02d-01', $year, $monthNum);

                // Controllo duplicato
                $exists = MileageLog::where('vehicle_id', $vehicle->id)
                    ->where('log_date', $dateStr)
                    ->exists();

                $warnings = [];
                if ($exists) {
                    $warnings[] = "Chilometraggio già presente per {$vehicle->internal_code} nel mese {$monthNum}/{$year}.";
                }

                $results[] = [
                    'row' => $index + 2,
                    'data' => [
                        '_vehicle_id' => $vehicle->id,
                        '_vehicle_label' => $vehicle->internal_code . ' - ' . $vehicle->license_plate,
                        '_date' => $dateStr,
                        '_label_date' => sprintf('%02d/%04d', $monthNum, $year),
                        '_mileage' => $kmValue,
                        '_exists' => $exists,
                        'veicolo' => $vehicleRef,
                        'mese' => sprintf('%02d/%04d', $monthNum, $year),
                        'chilometri' => $kmValue,
                    ],
                    'valid' => !$exists,
                    'errors' => [],
                    'warnings' => $warnings,
                ];
            }
        }

        return $results;
    }

    /**
     * Formato semplice: veicolo, mese, km
     */
    private function validateMileageLogsSimple(array $rows): array
    {
        $results = [];
        $vehiclesCache = [];

        foreach ($rows as $index => $row) {
            $result = [
                'row' => $index + 2,
                'data' => $row,
                'valid' => true,
                'warnings' => [],
                'errors' => [],
            ];

            $vehicleRef = $row['veicolo'] ?? $row['targa'] ?? $row['sigla'] ?? '';
            if (empty($vehicleRef)) {
                $result['valid'] = false;
                $result['errors'][] = 'Veicolo (targa/sigla) mancante.';
            } else {
                if (!isset($vehiclesCache[$vehicleRef])) {
                    $vehiclesCache[$vehicleRef] = Vehicle::where('license_plate', $vehicleRef)
                        ->orWhere('internal_code', $vehicleRef)
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
            }

            $mileage = $row['chilometri'] ?? $row['km'] ?? $row['mileage'] ?? '';
            if (empty($mileage) || !is_numeric($mileage)) {
                $result['valid'] = false;
                $result['errors'][] = "Chilometraggio \"{$mileage}\" non valido.";
            } else {
                $result['data']['_mileage'] = (int) $mileage;
            }

            $mese = $row['mese'] ?? $row['data'] ?? $row['periodo'] ?? '';
            if (empty($mese)) {
                $result['valid'] = false;
                $result['errors'][] = 'Mese/periodo mancante (usa MM/AAAA).';
            } else {
                $mese = str_replace(['-', '.'], '/', $mese);
                $parts = explode('/', $mese);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $month = (int) $parts[0];
                    $year = (int) $parts[1];
                    if ($month < 1 || $month > 12) {
                        $result['valid'] = false;
                        $result['errors'][] = "Mese \"{$month}\" non valido.";
                    } elseif ($year < 2000 || $year > 2100) {
                        $result['warnings'][] = "Anno \"{$year}\" sembra errato.";
                        $result['data']['_date'] = sprintf('%04d-%02d-01', $year, $month);
                        $result['data']['_label_date'] = sprintf('%02d/%04d', $month, $year);
                    } else {
                        $result['data']['_date'] = sprintf('%04d-%02d-01', $year, $month);
                        $result['data']['_label_date'] = sprintf('%02d/%04d', $month, $year);
                    }
                } else {
                    $result['valid'] = false;
                    $result['errors'][] = "Formato mese \"{$mese}\" non valido (usa MM/AAAA).";
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Valida righe guasti — supporta formato Excel-like con header variabili.
     */
    private function validateIssues(array $rows, ?string $vehicleRef = null): array
    {
        $results = [];
        $vehiclesCache = [];

        // Se il veicolo è passato come parametro (es. dal nome file "1727 - Guasti.csv")
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
                    $vehiclesCache[$vehicleRef] = Vehicle::where('internal_code', $vehicleRef)
                        ->orWhere('license_plate', $vehicleRef)
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

    private function importIssue(array $row): array
    {
        if (!isset($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        $vehicleId = $row['_vehicle_id'];
        $description = $row['_description'] ?? '';
        $status = $row['_status'] ?? 'open';
        $eventDate = $row['_date'] ?? Carbon::today()->toDateString();
        $appointmentDate = $row['_appointment_date'] ?? '';
        $providerName = $row['_provider_name'] ?? '';

        // Controllo se esiste già un guasto simile
        $exists = Issue::where('vehicle_id', $vehicleId)
            ->where('description', $description)
            ->where('event_date', $eventDate)
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

        // Se c'è data appuntamento, crea anche l'appuntamento
        if (!empty($appointmentDate)) {
            $parsedDate = $this->parseDate($appointmentDate);
            if (!$parsedDate) {
                return ['error' => 'Data appuntamento "' . $appointmentDate . '" non valida.'];
            }

            $providerId = null;
            if (!empty($providerName)) {
                $provider = Provider::where('name', 'like', '%' . $providerName . '%')->first();
                if ($provider) {
                    $providerId = $provider->id;
                }
            }

            $maintenanceRecord = \App\Models\MaintenanceRecord::create([
                'vehicle_id' => $vehicleId,
                'provider_id' => $providerId,
                'appointment_date' => $parsedDate->toDateString(),
                'activity_type' => 'Riparazione',
            ]);

            // Collega il guasto all'appuntamento
            $maintenanceRecord->issues()->attach($issue->id);
        }

        return ['success' => true];
    }

    private function importMileageLog(array $row): array
    {
        if (!isset($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        if (isset($row['_exists']) && $row['_exists']) {
            return ['error' => 'Chilometraggio già presente per ' . ($row['_label_date'] ?? $row['_date'])];
        }

        $exists = MileageLog::where('vehicle_id', $row['_vehicle_id'])
            ->where('log_date', $row['_date'])
            ->exists();

        if ($exists) {
            return ['error' => 'Chilometraggio già presente per ' . ($row['_label_date'] ?? $row['_date'])];
        }

        MileageLog::create([
            'vehicle_id' => $row['_vehicle_id'],
            'log_date' => $row['_date'],
            'mileage' => $row['_mileage'],
        ]);

        return ['success' => true];
    }
}
