<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * Login e rilascio token API. Stesse verifiche del login web
     * (LoginRequest/TwoFactorChallengeController): prima bastava
     * email+password, saltando sia il 2FA che la verifica email.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string|max:255',
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Le credenziali fornite non sono corrette.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Devi prima verificare il tuo indirizzo email.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled() && ! $this->twoFactorCodeIsValid($user, $request)) {
            throw ValidationException::withMessages([
                'code' => ['Codice di autenticazione a due fattori non valido o mancante.'],
            ]);
        }

        $token = $user->createToken($request->device_name ?? 'api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->activeGroup()?->pivot->role ?? 'member',
            ],
        ]);
    }

    /**
     * Logout: revoca token corrente.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout effettuato.']);
    }

    /**
     * Dettagli utente corrente.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Verifica il codice TOTP o un codice di recupero, stessa logica di
     * TwoFactorChallengeController::store() per il login web.
     */
    private function twoFactorCodeIsValid(User $user, Request $request): bool
    {
        if ($request->filled('code')) {
            return (new Google2FA)->verifyKey(
                $user->two_factor_secret,
                preg_replace('/\s+/', '', (string) $request->input('code'))
            );
        }

        if ($request->filled('recovery_code')) {
            return $user->redeemRecoveryCode(trim((string) $request->input('recovery_code')));
        }

        return false;
    }
}
