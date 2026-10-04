<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Secondo passo del login quando l'utente ha il 2FA attivo: LoginRequest
 * ha già verificato email/password e lasciato l'id utente in sessione
 * (login.2fa_user_id) senza completare Auth::login().
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login.2fa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($userId);
        $valid = false;

        if (! empty($data['code'])) {
            $valid = (new Google2FA())->verifyKey(
                $user->two_factor_secret,
                preg_replace('/\s+/', '', $data['code'])
            );
        } elseif (! empty($data['recovery_code'])) {
            $valid = $user->redeemRecoveryCode(trim($data['recovery_code']));
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Codice non valido.',
            ]);
        }

        $remember = $request->session()->pull('login.2fa_remember', false);
        $request->session()->forget('login.2fa_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
