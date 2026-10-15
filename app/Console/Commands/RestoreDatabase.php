<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RestoreDatabase extends Command
{
    protected $signature = 'app:restore-database
                            {filename? : Nome del file in backups/ da ripristinare (default: l\'ultimo disponibile)}
                            {--force : Salta la richiesta di conferma}';

    protected $description = 'Ripristina il database da un backup creato da app:backup-database. DISTRUTTIVO: sovrascrive tutti i dati attuali.';

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.uploads_disk'));
        $filename = $this->argument('filename');

        if (! $filename) {
            $latest = collect($disk->files('backups'))
                ->sortByDesc(fn ($f) => $disk->lastModified($f))
                ->first();

            if (! $latest) {
                $this->error('Nessun backup trovato.');

                return Command::FAILURE;
            }

            $filename = basename($latest);
        }

        $path = 'backups/'.$filename;

        if (! $disk->exists($path)) {
            $this->error("Backup non trovato: {$filename}");

            return Command::FAILURE;
        }

        $data = json_decode($disk->get($path), true);

        if (! is_array($data)) {
            $this->error("Il file di backup non è un JSON valido: {$filename}");

            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            "ATTENZIONE: questo sovrascriverà TUTTI i dati attuali con il contenuto di \"{$filename}\". Continuare?"
        )) {
            $this->info('Ripristino annullato.');

            return Command::SUCCESS;
        }

        $this->restore($data);

        $this->info("Database ripristinato da {$filename}.");

        return Command::SUCCESS;
    }

    /**
     * L'ordine delle tabelle nel backup non segue le dipendenze FK, quindi
     * i controlli vanno disattivati per l'intera operazione. Su MySQL,
     * SET FOREIGN_KEY_CHECKS=0 funziona per tutta la sessione senza bisogno
     * di una transazione (TRUNCATE lì fa comunque un commit implicito, una
     * transazione non darebbe atomicità reale). Su SQLite, PRAGMA
     * foreign_keys è invece un no-op se c'è già una transazione aperta (il
     * caso dei test, con RefreshDatabase) — PRAGMA defer_foreign_keys
     * funziona anche dentro una transazione, rimandando i controlli al
     * commit più esterno, quindi qui avvolgiamo esplicitamente in una
     * transazione solo per questo driver.
     */
    private function restore(array $data): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::transaction(function () use ($data) {
                DB::statement('PRAGMA defer_foreign_keys = ON');
                $this->truncateAndInsertAll($data);
            });

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->truncateAndInsertAll($data);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function truncateAndInsertAll(array $data): void
    {
        foreach ($data as $table => $rows) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        foreach ($data as $table => $rows) {
            if (! Schema::hasTable($table) || empty($rows)) {
                continue;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
}
