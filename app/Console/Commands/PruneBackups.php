<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneBackups extends Command
{
    protected $signature = 'app:prune-backups';

    protected $description = 'Elimina i backup del database più vecchi della soglia di conservazione';

    public const RETENTION_DAYS = 30;

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.uploads_disk'));
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $deleted = 0;

        foreach ($disk->files('backups') as $file) {
            if (now()->createFromTimestamp($disk->lastModified($file))->lt($cutoff)) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Backup eliminati: {$deleted}");

        return Command::SUCCESS;
    }
}
