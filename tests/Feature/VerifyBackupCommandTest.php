<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifyBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function putBackup(array $data, ?int $mtime = null): string
    {
        $path = 'backups/backup-test.json';
        Storage::disk('public')->put($path, json_encode($data));

        if ($mtime !== null) {
            touch(Storage::disk('public')->path($path), $mtime);
        }

        return $path;
    }

    public function test_fails_when_no_backup_exists(): void
    {
        Storage::fake('public');
        $this->admin();

        $this->artisan('app:verify-backup')->assertExitCode(1);

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_SYSTEM,
        ]);
    }

    public function test_fails_when_latest_backup_is_too_old(): void
    {
        Storage::fake('public');
        $this->admin();
        $this->putBackup(['vehicles' => []], now()->subDays(5)->timestamp);

        $this->artisan('app:verify-backup')->assertExitCode(1);
    }

    public function test_fails_when_backup_is_not_valid_json(): void
    {
        Storage::fake('public');
        $this->admin();
        Storage::disk('public')->put('backups/backup-bad.json', 'not valid json {{{');

        $this->artisan('app:verify-backup')->assertExitCode(1);
    }

    public function test_fails_when_a_live_table_is_empty_in_the_backup(): void
    {
        Storage::fake('public');
        $this->admin();

        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => Group::create(['name' => 'Gruppo test', 'invite_code' => Group::generateInviteCode()])->id,
        ]);

        // Backup con la tabella "vehicles" vuota, mentre nel DB live esiste
        // un veicolo: sintomo di un dump troncato.
        $this->putBackup(['vehicles' => []]);

        $this->artisan('app:verify-backup')->assertExitCode(1);
    }

    public function test_passes_when_backup_is_valid_and_recent(): void
    {
        Storage::fake('public');
        $this->admin();

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => Group::create(['name' => 'Gruppo test', 'invite_code' => Group::generateInviteCode()])->id,
        ]);

        $this->putBackup(['vehicles' => [$vehicle->toArray()]]);

        $this->artisan('app:verify-backup')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => Notification::TYPE_SYSTEM,
        ]);
    }
}
