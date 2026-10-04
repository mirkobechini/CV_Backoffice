<div class="head">
    <h3>{{ __('Autenticazione a due fattori') }}</h3>
</div>
<div class="body">
    @if (session('status') === 'two-factor-confirmed' || session('status') === 'recovery-codes-regenerated')
        <div class="alert warning" style="margin-bottom:14px;">
            <div style="width:100%;">
                <strong>{{ __('Salva questi codici di recupero in un posto sicuro.') }}</strong>
                <p style="margin:4px 0 10px;">
                    {{ __('Ogni codice può essere usato una sola volta per accedere se perdi il dispositivo con l\'app authenticator. Non verranno mostrati di nuovo.') }}
                </p>
                <div style="font-family:monospace; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    @foreach (session('recoveryCodes', []) as $code)
                        <span>{{ $code }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($user->hasTwoFactorEnabled())
        <p class="hint" style="margin-bottom:14px;">
            <span class="badge b-green">{{ __('Attivo') }}</span>
            {{ __('Il tuo account è protetto da un secondo fattore (app authenticator).') }}
        </p>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <form method="post" action="{{ route('two-factor.recovery-codes') }}" data-single-submit="true">
                @csrf
                <button type="submit" class="btn outline">
                    <i class="fa-solid fa-rotate"></i> {{ __('Rigenera codici di recupero') }}
                </button>
            </form>
            <button type="button" class="btn danger" data-bs-toggle="modal" data-bs-target="#disable-two-factor">
                <i class="fa-solid fa-lock-open"></i> {{ __('Disattiva') }}
            </button>
        </div>

        <div class="modal fade" id="disable-two-factor" tabindex="-1" data-bs-backdrop="static"
            data-bs-keyboard="false" role="dialog" aria-labelledby="disable-two-factor-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form method="post" action="{{ route('two-factor.destroy') }}" data-single-submit="true">
                        @csrf
                        @method('delete')
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="disable-two-factor-label">{{ __('Disattivare il 2FA?') }}</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Chiudi') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p style="margin-bottom:14px;">
                                {{ __('Il tuo account non sarà più protetto da un secondo fattore. Inserisci la password per confermare.') }}
                            </p>
                            <div class="field password-field" style="margin-bottom:0;">
                                <label for="two_factor_password">{{ __('Password') }}</label>
                                <input id="two_factor_password" name="password" type="password"
                                    class="input @error('password', 'twoFactor') is-invalid @enderror"
                                    placeholder="{{ __('Password') }}">
                                <button type="button" class="password-toggle" aria-label="{{ __('Mostra password') }}">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                @error('password', 'twoFactor')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-danger" data-loading-text="{{ __('Disattivazione...') }}">
                                {{ __('Disattiva 2FA') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @elseif ($twoFactorQrCode)
        <p class="hint" style="margin-bottom:14px;">
            {{ __("Scansiona il QR con un'app authenticator (Google Authenticator, Authy, 1Password...), poi inserisci il codice a 6 cifre per confermare.") }}
        </p>

        <div style="margin-bottom:14px; max-width:220px;">{!! $twoFactorQrCode !!}</div>

        <p class="hint" style="margin-bottom:14px;">
            {{ __('Oppure inserisci questa chiave manualmente') }}:
            <code>{{ $user->two_factor_secret }}</code>
        </p>

        <form method="post" action="{{ route('two-factor.confirm') }}" data-single-submit="true">
            @csrf
            <div class="field" style="max-width:220px;">
                <label for="two_factor_code">{{ __('Codice a 6 cifre') }}</label>
                <input id="two_factor_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                    class="input @error('code') is-invalid @enderror" maxlength="6">
                @error('code')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary" data-loading-text="{{ __('Verifica...') }}">
                    {{ __('Conferma attivazione') }}
                </button>
            </div>
        </form>
    @else
        <p class="hint" style="margin-bottom:14px;">
            {{ __('Non attivo. Aggiunge un secondo fattore (codice da un\'app authenticator) oltre alla password.') }}
        </p>

        <form method="post" action="{{ route('two-factor.store') }}" data-single-submit="true">
            @csrf
            <button type="submit" class="btn primary">
                <i class="fa-solid fa-shield-halved"></i> {{ __('Attiva 2FA') }}
            </button>
        </form>
    @endif
</div>
