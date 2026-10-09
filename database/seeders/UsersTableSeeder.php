<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Fixture di comodo per lo sviluppo locale (credenziali note e fisse):
     * non va eseguito fuori da local/testing, per evitare che un
     * `db:seed --class=UsersTableSeeder` lanciato per errore su un ambiente
     * reale crei un account con password nota (audit sicurezza 2026-10-09).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('UsersTableSeeder crea un account con password nota: eseguibile solo in locale/testing.');
        }

        $user = new User();
        $user->name = 'Mirko';
        $user->email = 'mirko@example.com';
        $user->password = bcrypt('password');
        $user->remember_token = 'tPEpcYrL3XZAtEAbnzdpM3rxAT7U06UYZAQIYG9jHxeOs33rN92pTXrvgfeS';
        $user->save();
    }
}
