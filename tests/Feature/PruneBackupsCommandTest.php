<?php

namespace Tests\Feature;

use App\Console\Commands\PruneBackups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneBackupsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function putBackup(string $filename, int $ageDays): void
    {
        $disk = Storage::disk('public');
        $path = 'backups/'.$filename;
        $disk->put($path, '{}');
        touch($disk->path($path), now()->subDays($ageDays)->timestamp);
    }

    public function test_deletes_backups_older_than_retention(): void
    {
        Storage::fake('public');
        $this->putBackup('old.json', PruneBackups::RETENTION_DAYS + 5);
        $this->putBackup('recent.json', 2);

        $this->artisan('app:prune-backups')->assertExitCode(0);

        Storage::disk('public')->assertMissing('backups/old.json');
        Storage::disk('public')->assertExists('backups/recent.json');
    }

    public function test_keeps_backups_within_retention(): void
    {
        Storage::fake('public');
        $this->putBackup('a.json', PruneBackups::RETENTION_DAYS - 1);
        $this->putBackup('b.json', 1);

        $this->artisan('app:prune-backups')->assertExitCode(0);

        Storage::disk('public')->assertExists('backups/a.json');
        Storage::disk('public')->assertExists('backups/b.json');
    }

    public function test_does_nothing_when_no_backups_exist(): void
    {
        Storage::fake('public');

        $this->artisan('app:prune-backups')->assertExitCode(0);
    }
}
