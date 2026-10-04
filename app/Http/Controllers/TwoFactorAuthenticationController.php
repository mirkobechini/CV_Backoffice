<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

/**
 * Attivazione/conferma/disattivazione del 2FA (TOTP) dal profilo utente.
 * Non integrato con Fortify (il progetto usa Breeze): costruito sopra
 * pragmarx/google2fa, la libreria TOTP di riferimento per Laravel, con un
 * flusso minimo — genera, conferma con un codice, mostra i codici di
 * recupero una sola volta, disattiva con conferma password.
 */
class TwoFactorAuthenticationController extends Controller
{
    /**
     * Genera un nuovo segreto (non ancora confermato) e lo salva: la
     * pagina profilo mostra il QR/chiave manuale finché non viene
     * confermato con un codice (vedi confirm()).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => (new Google2FA())->generateSecretKey(),
        ])->save();

        return back();
    }

    /**
     * Conferma l'attivazione con un codice generato dall'app authenticator.
     * Solo a questo punto il 2FA diventa effettivo (two_factor_confirmed_at)
     * e vengono generati i codici di recupero, mostrati una sola volta.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $user->two_factor_secret) {
            return back()->withErrors(['code' => 'Nessuna attivazione in corso: ricomincia dal primo passo.']);
        }

        $valid = (new Google2FA())->verifyKey(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $request->input('code'))
        );

        if (! $valid) {
            return back()->withErrors(['code' => 'Codice non valido o scaduto.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $recoveryCodes = $user->generateRecoveryCodes();

        return back()->with('status', 'two-factor-confirmed')->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Disattiva il 2FA, richiedendo la password corrente (stesso schema di
     * ProfileController::destroy() per l'eliminazione account).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('twoFactor', [
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->disableTwoFactor();

        return back()->with('status', 'two-factor-disabled');
    }

    /**
     * Rigenera i codici di recupero (invalida quelli esistenti), solo se
     * il 2FA è già attivo e confermato.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back();
        }

        $recoveryCodes = $user->generateRecoveryCodes();

        return back()->with('status', 'recovery-codes-regenerated')->with('recoveryCodes', $recoveryCodes);
    }
}
