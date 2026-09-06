<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esporta un backup del database in formato JSON';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tables = DB::select('SELECT name FROM sqlite_master WHERE type="table" AND name NOT LIKE "sqlite_%"');

        $data = [];

        foreach ($tables as $table) {
            $tableName = $table->name;
            $data[$tableName] = DB::table($tableName)->get()->toArray();
        }

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk('local')->put('backups/'.$filename, $json);

        $this->info("Backup creato: {$filename}");

        return Command::SUCCESS;
    }
}
