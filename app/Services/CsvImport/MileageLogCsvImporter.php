<?php

namespace App\Services\CsvImport;

use App\Models\MileageLog;
use App\Models\Vehicle;

class MileageLogCsvImporter extends AbstractCsvImporter
{
    private const MESI = [
        'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
        'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
    ];

    /**
     * Valida righe di chilometraggi — supporta formato pivot (mese come colonne).
     */
    public function validate(array $rows, array $options = []): array
    {
        $importYear = $options['import_year'] ?? (int) date('Y');
        if ($importYear === 0) {
            $importYear = (int) date('Y');
        }

        // Determina se è formato pivot
        $sampleHeaders = array_keys($rows[0] ?? []);
        $isPivot = $this->isPivotFormat($sampleHeaders);

        if ($isPivot) {
            return $this->validatePivot($rows, $importYear);
        }

        // Formato semplice (veicolo, mese, km)
        return $this->validateSimple($rows);
    }

    public function import(array $row): array
    {
        if (!isset($row['_vehicle_id']) || !$this->vehicleBelongsToCurrentUser($row['_vehicle_id'])) {
            return ['error' => 'Veicolo non valido'];
        }

        if (isset($row['_exists']) && $row['_exists']) {
            return ['error' => 'Chilometraggio già presente per ' . ($row['_label_date'] ?? $row['_date'])];
        }

        // whereDate() invece di where(): un confronto di uguaglianza esatta
        // su stringa falliva sempre se la colonna conteneva un suffisso
        // orario, non rilevando mai il duplicato.
        $exists = MileageLog::where('vehicle_id', $row['_vehicle_id'])
            ->whereDate('log_date', $row['_date'])
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

    /**
     * Rileva se il CSV è in formato pivot (con colonne mesi).
     */
    private function isPivotFormat(array $headers): bool
    {
        foreach ($headers as $h) {
            if (in_array(mb_strtolower(trim($h)), self::MESI, true)) {
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
        $index = array_search(mb_strtolower(trim($name)), self::MESI, true);
        return $index === false ? null : $index + 1;
    }

    /**
     * Formato pivot: righe=veicoli, colonne=mesi
     * Header: SIGLA, MEZZI, TARGA, GENNAIO, FEBBRAIO, ...
     */
    private function validatePivot(array $rows, int $year): array
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

        // Una sola query per tutti i chilometraggi dell'anno (del proprio
        // gruppo), invece di una query exists() per ogni cella veicolo×mese
        // del CSV — un import tipico (decine di veicoli × 12 mesi) arrivava
        // a centinaia di query solo per il controllo duplicati.
        $existingLogDates = MileageLog::whereYear('log_date', $year)
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->get(['vehicle_id', 'log_date'])
            ->map(fn ($log) => $log->vehicle_id . '|' . $log->log_date->toDateString())
            ->flip();

        foreach ($rows as $index => $row) {
            $sigla = $row['SIGLA'] ?? $row['sigla'] ?? '';
            $targa = $row['TARGA'] ?? $row['targa'] ?? '';

            $vehicleRef = $sigla ?: $targa;
            if (empty($vehicleRef)) {
                continue;
            }

            // Trova veicolo (solo nel proprio gruppo: senza forCurrentUser()
            // qui, un capo poteva importare dati su un veicolo di un altro
            // gruppo indovinandone sigla/targa).
            if (!isset($vehiclesCache[$vehicleRef])) {
                $vehiclesCache[$vehicleRef] = Vehicle::where(fn ($q) => $q->where('internal_code', $vehicleRef)
                    ->orWhere('license_plate', $vehicleRef))
                    ->forCurrentUser()
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
                $exists = isset($existingLogDates[$vehicle->id . '|' . $dateStr]);

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
    private function validateSimple(array $rows): array
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
                    // forCurrentUser(): vedi commento in validatePivot().
                    $vehiclesCache[$vehicleRef] = Vehicle::where(fn ($q) => $q->where('license_plate', $vehicleRef)
                        ->orWhere('internal_code', $vehicleRef))
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
}
