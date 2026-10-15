<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_trips_through_a_real_backup(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $this->artisan('app:backup-database')->assertExitCode(0);

        $group->update(['name' => 'Modificato per errore']);

        $this->artisan('app:restore-database', ['--force' => true])->assertExitCode(0);

        $this->assertSame('Gruppo A', $group->fresh()->name);
    }

    public function test_restores_a_specific_filename(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $this->artisan('app:backup-database')->assertExitCode(0);
        $filename = basename(collect(Storage::disk('public')->files('backups'))->first());

        $group->update(['name' => 'Modificato per errore']);

        $this->artisan('app:restore-database', ['filename' => $filename, '--force' => true])->assertExitCode(0);

        $this->assertSame('Gruppo A', $group->fresh()->name);
    }

    public function test_preserves_group_membership_across_foreign_keys(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        $this->artisan('app:backup-database')->assertExitCode(0);
        $this->artisan('app:restore-database', ['--force' => true])->assertExitCode(0);

        $this->assertSame(Group::ROLE_CAPO, $user->fresh()->roleIn($group->fresh()));
    }

    public function test_fails_when_no_backup_exists(): void
    {
        Storage::fake('public');

        $this->artisan('app:restore-database', ['--force' => true])->assertExitCode(1);
    }

    public function test_fails_when_filename_does_not_exist(): void
    {
        Storage::fake('public');

        $this->artisan('app:restore-database', ['filename' => 'nonexistent.json', '--force' => true])
            ->assertExitCode(1);
    }

    public function test_fails_when_backup_is_not_valid_json(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('backups/bad.json', 'not valid json {{{');

        $this->artisan('app:restore-database', ['filename' => 'bad.json', '--force' => true])
            ->assertExitCode(1);
    }

    public function test_declining_confirmation_leaves_data_unchanged(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $this->artisan('app:backup-database')->assertExitCode(0);
        $group->update(['name' => 'Modificato per errore']);

        $this->artisan('app:restore-database')
            ->expectsConfirmation('ATTENZIONE: questo sovrascriverà TUTTI i dati attuali con il contenuto di "'.basename(collect(Storage::disk('public')->files('backups'))->first()).'". Continuare?', 'no')
            ->assertExitCode(0);

        $this->assertSame('Modificato per errore', $group->fresh()->name);
    }
}
