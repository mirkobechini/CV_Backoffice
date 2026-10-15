<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperadmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:superadmin {email : Email di un utente già esistente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promuove un utente esistente a superadmin (gestione licenze di tutte le associazioni)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Nessun utente trovato con email {$email}.");

            return Command::FAILURE;
        }

        if ($user->is_superadmin) {
            $this->info("{$user->name} è già superadmin.");

            return Command::SUCCESS;
        }

        $user->forceFill(['is_superadmin' => true])->save();

        $this->info("✅ {$user->name} ({$user->email}) è ora superadmin.");

        return Command::SUCCESS;
    }
}
