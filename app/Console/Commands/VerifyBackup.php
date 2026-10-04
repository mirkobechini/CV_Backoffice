<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class VerifyBackup extends Command
{
    protected $signature = 'app:verify-backup';

    protected $description = "Verifica che l'ultimo backup del database sia valido e non troncato";

    /**
     * Un backup più vecchio di questo viene considerato mancante: se
     * nessun backup recente esiste, c'è poco da verificare nel contenuto,
     * il problema è che non viene (più) creato.
     */
    private const MAX_BACKUP_AGE_DAYS = 2;

    public function handle(NotificationService $notifications): int
    {
        $disk = Storage::disk(config('filesystems.uploads_disk'));
        $files = collect($disk->files('backups'))->sortByDesc(fn ($f) => $disk->lastModified($f));

        $latest = $files->first();

        if (! $latest) {
            return $this->failVerification($notifications, 'Nessun backup trovato: il backup automatico non ha ancora creato nulla.');
        }

        // abs(): Carbon 3 cambia il default di $absolute a false (a
        // differenza di Carbon 2), quindi diffInDays() da solo tornerebbe
        // un valore negativo quando il backup è nel passato.
        $ageDays = (int) abs(now()->diffInDays(now()->createFromTimestamp($disk->lastModified($latest))));
        if ($ageDays > self::MAX_BACKUP_AGE_DAYS) {
            return $this->failVerification($notifications, "L'ultimo backup ({$latest}) ha {$ageDays} giorni: il backup automatico sembra non essere più in esecuzione.");
        }

        $content = $disk->get($latest);
        $data = json_decode($content, true);

        if ($data === null || ! is_array($data)) {
            return $this->failVerification($notifications, "L'ultimo backup ({$latest}) non è un JSON valido: potrebbe essere troncato o corrotto.");
        }

        // Una tabella che oggi ha righe ma nel backup risulta vuota o
        // assente è il sintomo tipico di un dump troncato (es. interrotto a
        // metà, o un errore silenzioso durante l'export di quella tabella).
        $truncatedTables = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (! array_key_exists($table, $data)) {
                continue;
            }

            $liveCount = DB::table($table)->count();
            $backupCount = is_array($data[$table]) ? count($data[$table]) : 0;

            if ($liveCount > 0 && $backupCount === 0) {
                $truncatedTables[] = $table;
            }
        }

        if (! empty($truncatedTables)) {
            $tableList = implode(', ', $truncatedTables);
            return $this->failVerification($notifications, "L'ultimo backup ({$latest}) ha tabelle vuote che oggi contengono dati: {$tableList}.");
        }

        $this->info("Backup verificato: {$latest}");

        return Command::SUCCESS;
    }

    private function failVerification(NotificationService $notifications, string $reason): int
    {
        Log::error("Verifica backup fallita: {$reason}");

        $notifications->notifyAdmins(
            Notification::TYPE_SYSTEM,
            'Verifica backup fallita',
            $reason,
        );

        $this->error($reason);

        return Command::FAILURE;
    }
}
