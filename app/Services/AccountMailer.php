<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Invio del link per impostare/reimpostare la password e della verifica
 * email, con gestione dei fallimenti di invio (SMTP/Resend non
 * raggiungibile, chiave non valida, ecc.). Prima di questa estrazione, un
 * fallimento qui faceva 500 l'intera richiesta (creazione utente, "password
 * dimenticata", creazione gruppo da superadmin) anche quando l'azione
 * principale era già andata a buon fine — l'utente restava creato ma senza
 * modo di saperlo, e "password dimenticata" risultava rotto per chiunque.
 */
class AccountMailer
{
    public function sendPasswordSetupLink(User $user): bool
    {
        try {
            Password::sendResetLink(['email' => $user->email]);

            return true;
        } catch (Throwable $e) {
            Log::error("AccountMailer: invio link password fallito per {$user->email}: {$e->getMessage()}", ['exception' => $e]);

            return false;
        }
    }

    public function sendEmailVerification(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            Log::error("AccountMailer: invio verifica email fallito per {$user->email}: {$e->getMessage()}", ['exception' => $e]);

            return false;
        }
    }
}
