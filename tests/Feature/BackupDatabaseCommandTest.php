<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_valid_json_backup_with_table_data(): void
    {
        Storage::fake('public');

        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => Group::create(['name' => 'Gruppo test', 'invite_code' => Group::generateInviteCode()])->id,
        ]);

        $this->artisan('app:backup-database')->assertExitCode(0);

        $files = Storage::disk('public')->files('backups');
        $this->assertCount(1, $files);

        $data = json_decode(Storage::disk('public')->get($files[0]), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('vehicles', $data);
        $this->assertCount(1, $data['vehicles']);
    }
}
