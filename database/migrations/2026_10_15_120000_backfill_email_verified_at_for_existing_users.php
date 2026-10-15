<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User::class ha appena iniziato a implementare MustVerifyEmail
     * (prima il middleware 'verified' su /dashboard e /admin/* era un
     * no-op per tutti, mai verificato nessuno): senza questo backfill,
     * ogni account già esistente — Mirko compreso — si troverebbe
     * bloccato fuori al prossimo login, perché non ha mai avuto motivo
     * di "verificare" un'email che funzionava già. Solo i nuovi account
     * creati da qui in avanti devono davvero passare dalla verifica.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Non reversibile in modo significativo: non c'è modo di distinguere
        // chi era "mai verificato prima" da chi si è verificato nel
        // frattempo nel modo giusto.
    }
};
