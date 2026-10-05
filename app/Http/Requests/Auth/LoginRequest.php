<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Verifica le credenziali e completa l'accesso, oppure — se l'utente
     * ha il 2FA attivo — sospende l'accesso in attesa del codice (vedi
     * TwoFactorChallengeController), senza stabilire ancora una sessione
     * autenticata.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($this->only('email', 'password'));

        if (! $user || ! $provider->validateCredentials($user, $this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($user->hasTwoFactorEnabled()) {
            $this->session()->put('login.2fa_user_id', $user->getKey());
            $this->session()->put('login.2fa_remember', $this->boolean('remember'));

            return;
        }

        Auth::login($user, $this->boolean('remember'));
    }

    /**
     * True se le credenziali erano valide ma l'accesso è in attesa del
     * codice 2FA (vedi authenticate()).
     */
    public function requiresTwoFactorChallenge(): bool
    {
        return $this->session()->has('login.2fa_user_id');
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
