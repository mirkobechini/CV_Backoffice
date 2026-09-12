@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Reimposta password') }}</h1>
    <p class="hint" style="margin-bottom:20px;">{{ __('Scegli una nuova password per il tuo account.') }}</p>

    <form method="POST" action="{{ route('password.store') }}" data-single-submit="true">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" type="email" class="input @error('email') is-invalid @enderror" name="email"
                value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field password-field">
            <label for="password">{{ __('Nuova password') }}</label>
            <input id="password" type="password" class="input @error('password') is-invalid @enderror"
                name="password" required autocomplete="new-password">
            <button type="button" class="password-toggle" aria-label="{{ __('Mostra password') }}">
                <i class="fa-solid fa-eye"></i>
            </button>
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field password-field">
            <label for="password_confirmation">{{ __('Conferma password') }}</label>
            <input id="password_confirmation" type="password" class="input" name="password_confirmation" required
                autocomplete="new-password">
            <button type="button" class="password-toggle" aria-label="{{ __('Mostra password') }}">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>

        <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}"
            style="width:100%; justify-content:center;">
            {{ __('Reimposta password') }}
        </button>
    </form>
@endsection
