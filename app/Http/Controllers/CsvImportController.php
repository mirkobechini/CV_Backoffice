<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\MileageLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CsvImportController extends Controller
{
    /**
     * Mostra la pagina di import.
     */
    public function index()
    {
        return view('admin.csv-import.index');
    }

    /**
     * Analizza il CSV e mostra anteprima con validazione.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'entity' => 'required|in:issues,mileage-logs',
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $entity = $request->entity;
        $rows = $this->parseCsv($request->file('csv_file'));

        if (empty($rows)) {
            return back()->with('status_error', 'Il file CSV è vuoto o il formato non è valido.');
        }

        $results = match ($entity) {
            'issues' => $this->validateIssues($rows),
            'mileage-logs' => $this->validateMileageLogs($rows),
            default => [],
        };

        return view('admin.csv-import.preview', compact('entity', 'results', 'rows'));
    }

    /**
     * Conferma l'import dopo la preview.
     */
    public function confirm(Request $request)
    {
        $entity = $request->entity;
        $rows = json_decode($request->rows_json, true);

        if (empty($rows)) {
            return redirect()->route('admin.csv-import.index')
                ->with('status_error', 'Nessun dato da importare.');
        }

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($entity, $rows, &$imported, &$errors) {
            foreach ($rows as $index => $row) {
                $result = match ($entity) {
                    'issues' => $this->importIssue($row),
                    'mileage-logs' => $this->importMileageLog($row),
                    default => ['error' => 'Entità sconosciuta'],
                };

                if (isset($result['error'])) {
                    $errors[] = "Riga " . ($index + 2) . ": " . $result['error'];
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

    /**
     * Parsa il file CSV in un array associativo.
     */
    private function parseCsv($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return $rows;
        }

        // Legge header (prima riga)
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return $rows;
        }

        // Normalizza header: rimuovi BOM, trim, lowercase
        $headers = array_map(fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), $headers);

        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($line[$i]) ? trim($line[$i]) : '';
            }
            // Salta righe completamente vuote
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Valida righe di guasti.
     */
    private function validateIssues(array $rows): array
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

            // description
            if (empty($row['descrizione'] ?? '')) {
                $result['valid'] = false;
                $result['errors'][] = 'Descrizione mancante.';
            }

            // vehicle — cerca per targa o sigla
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

            // status
            $status = $row['stato'] ?? $row['status'] ?? '';
            if (empty($status)) {
                $result['data']['_status'] = 'open';
            } elseif (!in_array($status, ['open', 'in_progress', 'closed', 'aperto', 'in lavorazione', 'risolto'])) {
                $result['warnings'][] = "Stato \"{$status}\" non riconosciuto, verrà impostato a 'open'.";
                $result['data']['_status'] = 'open';
            } else {
                $result['data']['_status'] = match ($status) {
                    'aperto' => 'open',
                    'in lavorazione' => 'in_progress',
                    'risolto' => 'closed',
                    default => $status,
                };
            }

            // event_date
            $date = $row['data'] ?? $row['data_evento'] ?? '';
            if (empty($date)) {
                $result['data']['_date'] = Carbon::today()->toDateString();
                $result['warnings'][] = 'Data non specificata, usata data odierna.';
            } else {
                try {
                    $result['data']['_date'] = Carbon::parse($date)->toDateString();
                } catch (\Exception $e) {
                    $result['valid'] = false;
                    $result['errors'][] = "Data \"{$date}\" non valida (usa formato GG/MM/AAAA o AAAA-MM-GG).";
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Valida righe di chilometraggi.
     */
    private function validateMileageLogs(array $rows): array
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

            // vehicle
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

            // mileage
            $mileage = $row['chilometri'] ?? $row['km'] ?? $row['mileage'] ?? '';
            if (empty($mileage) || !is_numeric($mileage)) {
                $result['valid'] = false;
                $result['errors'][] = "Chilometraggio \"{$mileage}\" non valido.";
            } else {
                $result['data']['_mileage'] = (int) $mileage;
            }

            // month/year
            $mese = $row['mese'] ?? $row['data'] ?? $row['periodo'] ?? '';
            if (empty($mese)) {
                $result['valid'] = false;
                $result['errors'][] = 'Mese/periodo mancante (usa MM/AAAA).';
            } else {
                // Prova vari formati: MM/AAAA, MM-AAAA, AAAA-MM
                $mese = str_replace(['-', '.'], '/', $mese);
                $parts = explode('/', $mese);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $month = (int) $parts[0];
                    $year = (int) $parts[1];
                    if ($month < 1 || $month > 12) {
                        $result['valid'] = false;
                        $result['errors'][] = "Mese \"{$month}\" non valido (usa MM/AAAA).";
                    } elseif ($year < 2000 || $year > 2100) {
                        $result['errors'][] = "Anno \"{$year}\" sembra errato.";
                    } else {
                        $result['data']['_date'] = sprintf('%04d-%02d-01', $year, $month);
                        $result['data']['_label_date'] = sprintf('%02d/%04d', $month, $year);
                    }
                } else {
                    $result['valid'] = false;
                    $result['errors'][] = "Formato mese \"{$mese}\" non valido (usa MM/AAAA).";
                }
            }

            // Controllo km > ultimo log (solo se tutto valido)
            if ($result['valid'] && isset($result['data']['_vehicle_id']) && isset($result['data']['_mileage'])) {
                $lastKm = MileageLog::where('vehicle_id', $result['data']['_vehicle_id'])
                    ->orderByDesc('log_date')
                    ->value('mileage');
                if ($lastKm !== null && $result['data']['_mileage'] < $lastKm) {
                    $result['warnings'][] = "{$result['data']['_mileage']} km è inferiore all'ultimo registrato ({$lastKm} km).";
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Importa una riga guasto (usato in fase di confirm).
     */
    private function importIssue(array $row): array
    {
        if (!isset($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        Issue::create([
            'vehicle_id' => $row['_vehicle_id'],
            'description' => $row['descrizione'] ?? '',
            'status' => $row['_status'] ?? 'open',
            'event_date' => $row['_date'] ?? Carbon::today()->toDateString(),
        ]);

        return ['success' => true];
    }

    /**
     * Importa una riga chilometraggio (usato in fase di confirm).
     */
    private function importMileageLog(array $row): array
    {
        if (!isset($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        // Controlla se esiste già un log per stesso veicolo e stesso mese
        $exists = MileageLog::where('vehicle_id', $row['_vehicle_id'])
            ->where('log_date', $row['_date'])
            ->exists();

        if ($exists) {
            return ['error' => 'Chilometraggio già presente per ' . ($row['_label_date'] ?? $row['_date']) . '.'];
        }

        MileageLog::create([
            'vehicle_id' => $row['_vehicle_id'],
            'log_date' => $row['_date'],
            'mileage' => $row['_mileage'],
        ]);

        return ['success' => true];
    }
}
