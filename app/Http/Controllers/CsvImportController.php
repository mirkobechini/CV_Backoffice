<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Services\CsvImport\CsvImporter;
use App\Services\CsvImport\IssueCsvImporter;
use App\Services\CsvImport\MileageLogCsvImporter;
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
        $this->authorize('create', Issue::class);
        try {
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

            $results = $this->importerFor($entity)->validate($rows, [
                'vehicle_ref' => $request->vehicle_ref,
                'import_year' => $importYear,
            ]);

            return view('admin.csv-import.preview', compact('entity', 'results', 'rows'));
        } catch (\Throwable $e) {
            return back()->with('status_error', 'Errore nella validazione: ' . $e->getMessage());
        }
    }

    public function confirm(Request $request)
    {
        $this->authorize('create', Issue::class);
        $entity = $request->entity;
        $editable = $request->input('editable', []);

        if (empty($editable)) {
            return redirect()->route('admin.csv-import.index')
                ->with('status_error', 'Nessun dato da importare.');
        }

        $importer = $this->importerFor($entity);
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($importer, $editable, &$imported, &$errors) {
            foreach ($editable as $row) {
                // Salta i record esclusi dall'utente
                if (!empty($row['_skip'])) {
                    continue;
                }
                // Solo record validi
                if (empty($row['_valid']) || $row['_valid'] !== '1') {
                    continue;
                }

                $r = $importer->import($row);
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

    private function importerFor(string $entity): CsvImporter
    {
        return match ($entity) {
            'issues' => new IssueCsvImporter(),
            'mileage-logs' => new MileageLogCsvImporter(),
        };
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

        // Assegna nomi univoci alle colonne senza header (es. _col_0, _col_1, ...)
        $counter = 0;
        foreach ($headers as $i => $h) {
            if ($h === '') {
                $headers[$i] = '_col_' . $counter++;
            }
        }

        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = isset($line[$i]) ? trim($line[$i]) : '';
            }
            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }
}
