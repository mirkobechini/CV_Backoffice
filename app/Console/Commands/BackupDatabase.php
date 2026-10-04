<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Esporta un backup del database in formato JSON';

    public function handle(): int
    {
        // Schema::getTableListing() funziona sia su MySQL (produzione) che
        // su SQLite (sviluppo/test): la query precedente ('SELECT name FROM
        // sqlite_master') era specifica di SQLite e falliva sempre su MySQL
        // — il backup non ha mai funzionato in produzione, senza che
        // nessuno se ne accorgesse (nessun test lo copriva).
        // schemaQualified: false — altrimenti su un server con più schema
        // visibili i nomi tornano come "nome_database.tabella", e
        // DB::table() su quel nome composito fallisce.
        $tables = Schema::getTableListing(schemaQualified: false);

        $data = [];

        foreach ($tables as $tableName) {
            $data[$tableName] = DB::table($tableName)->get()->toArray();
        }

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Stesso disco configurato per gli upload utente (UPLOADS_DISK,
        // R2 in produzione): il disco "local" su cui veniva scritto prima
        // non è persistente sul compute di Laravel Cloud, esattamente come
        // già documentato per le carte di circolazione (vedi DEPLOY.md) —
        // anche un backup riuscito sarebbe sparito al riavvio/redeploy
        // successivo.
        Storage::disk(config('filesystems.uploads_disk'))->put('backups/'.$filename, $json);

        $this->info("Backup creato: {$filename}");

        return Command::SUCCESS;
    }
}
