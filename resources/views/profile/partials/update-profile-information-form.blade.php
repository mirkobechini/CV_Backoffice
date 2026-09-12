<div class="head">
    <h3>{{ __('Informazioni profilo') }}</h3>
</div>
<div class="body">
    <p class="hint" style="margin-bottom:14px;">
        {{ __("Aggiorna il nome e l'indirizzo email del tuo account.") }}</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}" data-single-submit="true">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" data-single-submit="true">
        @csrf
        @method('patch')

        <div class="field">
            <label for="name">{{ __('Nome') }}</label>
            <input class="input @error('name') is-invalid @enderror" type="text" name="name" id="name"
                autocomplete="name" value="{{ old('name', $user->name) }}" required autofocus>
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="input @error('email') is-invalid @enderror"
                value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="hint" style="margin-top:8px;">
                    {{ __('Il tuo indirizzo email non è verificato.') }}
                    <button form="send-verification" class="btn sm" type="submit"
                        data-loading-text="{{ __('Invio...') }}" style="margin-left:6px;">
                        {{ __('Invia di nuovo la mail di verifica') }}
                    </button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <div class="alert success" style="margin-top:10px;">
                        {{ __('Una nuova mail di verifica è stata inviata al tuo indirizzo email.') }}
                    </div>
                @endif
            @endif
        </div>

        <div class="form-actions">
            <button class="btn primary" type="submit" data-loading-text="{{ __('Salvataggio...') }}">
                {{ __('Salva') }}
            </button>

            @if (session('status') === 'profile-updated')
                <span class="hint">{{ __('Salvato.') }}</span>
            @endif
        </div>
    </form>
</div>
