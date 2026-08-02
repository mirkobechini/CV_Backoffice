<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin
                            {--name=Admin : Nome dell\'utente admin}
                            {--email= : Email dell\'utente admin}
                            {--password= : Password dell\'utente admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea il primo utente amministratore (usa --email e --password per modalità non interattiva)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->error('Esiste già un utente amministratore.');
            $this->info('Puoi crearne un altro modificando il ruolo dalla dashboard o via tinker.');
            return Command::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Nome', 'Admin');
        $email = $this->option('email') ?? $this->ask('Email');
        $password = $this->option('password') ?? $this->secret('Password');

        if (empty($email)) {
            $this->error('L\'email è obbligatoria.');
            return Command::FAILURE;
        }

        if (empty($password)) {
            $this->error('La password è obbligatoria.');
            return Command::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'admin',
        ]);

        $this->info("✅ Utente amministratore creato con successo!");
        $this->table(
            ['Nome', 'Email', 'Ruolo'],
            [[$user->name, $user->email, $user->role]]
        );

        return Command::SUCCESS;
    }
}
