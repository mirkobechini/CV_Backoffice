<?php

namespace App\Http\Controllers\Concerns;

/**
 * Streaming CSV con BOM (supporto Excel UTF-8) e neutralizzazione
 * dell'iniezione di formule, condiviso tra CsvExportController e ogni
 * altro controller che esporta CSV (prima duplicato, audit 2026-10-19 F5).
 */
trait StreamsCsv
{
    private function downloadCsv(string $filename, array $headers, iterable $rows)
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');

            // BOM per supporto Excel UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($file, array_map([$this, 'escapeCsvFormula'], $row), ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Neutralizza l'iniezione di formule CSV (Excel/Sheets/LibreOffice
     * interpretano come formula qualunque cella che inizia con =, +, -, @,
     * tab o ritorno a capo): un campo testo libero come descrizione guasto
     * o nome fornitore, scritto da un membro qualunque del gruppo, poteva
     * eseguire una formula quando il CSV veniva riaperto da qualcun altro.
     * Anteponendo un apice, il programma di fogli di calcolo lo tratta
     * come testo letterale invece che come formula.
     */
    private function escapeCsvFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
