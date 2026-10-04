@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Verifica in due passaggi') }}</h1>
    <p class="hint" style="margin-bottom:20px;">
        {{ __("Inserisci il codice a 6 cifre dall'app authenticator, oppure uno dei tuoi codici di recupero.") }}
    </p>

    <form method="POST" action="{{ route('two-factor.challenge') }}" data-single-submit="true">
        @csrf

        <div class="field">
            <label for="code">{{ __('Codice a 6 cifre') }}</label>
            <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                class="input @error('code') is-invalid @enderror" name="code" autofocus>
            @error('code')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <p class="hint" style="margin:10px 0;">{{ __('oppure') }}</p>

        <div class="field">
            <label for="recovery_code">{{ __('Codice di recupero') }}</label>
            <input id="recovery_code" type="text" class="input" name="recovery_code" autocomplete="off">
        </div>

        <button type="submit" class="btn primary lg" data-loading-text="{{ __('Verifica...') }}"
            style="width:100%; justify-content:center; margin-top:10px;">
            {{ __('Verifica') }}
        </button>
    </form>
@endsection
