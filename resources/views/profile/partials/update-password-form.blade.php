<div class="head">
    <h3>{{ __('Aggiorna password') }}</h3>
</div>
<div class="body">
    <p class="hint" style="margin-bottom:14px;">
        {{ __('Usa una password lunga e casuale per mantenere sicuro il tuo account.') }}</p>

    <form method="post" action="{{ route('password.update') }}" data-single-submit="true">
        @csrf
        @method('put')

        <div class="field">
            <label for="current_password">{{ __('Password attuale') }}</label>
            <input class="input @error('current_password', 'updatePassword') is-invalid @enderror" type="password"
                name="current_password" id="current_password" autocomplete="current-password">
            @error('current_password', 'updatePassword')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password">{{ __('Nuova password') }}</label>
            <input class="input @error('password', 'updatePassword') is-invalid @enderror" type="password"
                name="password" id="password" autocomplete="new-password">
            @error('password', 'updatePassword')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">{{ __('Conferma password') }}</label>
            <input class="input" type="password" name="password_confirmation" id="password_confirmation"
                autocomplete="new-password">
            @error('password_confirmation', 'updatePassword')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio...') }}">
                {{ __('Salva') }}
            </button>

            @if (session('status') === 'password-updated')
                <span class="hint">{{ __('Salvato.') }}</span>
            @endif
        </div>
    </form>
</div>
