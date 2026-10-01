<?php

namespace App\Services\CsvImport;

interface CsvImporter
{
    /**
     * Valida le righe grezze del CSV (parseCsv()), restituendo per ognuna
     * i dati arricchiti (es. _vehicle_id risolto) e l'esito (valid/errors/
     * warnings) da mostrare nella pagina di anteprima.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $options  parametri specifici per entità (es. vehicle_ref, import_year)
     * @return array<int, array{row: int, data: array, valid: bool, errors: array, warnings: array}>
     */
    public function validate(array $rows, array $options = []): array;

    /**
     * Importa una singola riga già validata/confermata dall'utente
     * (il campo 'data' di validate(), con eventuali modifiche manuali).
     *
     * @param  array<string, mixed>  $row
     * @return array{success?: bool, error?: string}
     */
    public function import(array $row): array;
}
