@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Accedi') }}</h1>
    <p class="hint" style="margin-bottom:20px;">{{ __('Inserisci le tue credenziali per accedere.') }}</p>

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" data-single-submit="true">
        @csrf

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" type="email" class="input @error('email') is-invalid @enderror" name="email"
                value="{{ old('email') }}" required autocomplete="email" autofocus>
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input id="password" type="password" class="input @error('password') is-invalid @enderror"
                name="password" required autocomplete="current-password">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <label class="check" style="margin-bottom:18px;">
            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
            <div class="label">{{ __('Ricordami') }}</div>
        </label>

        <button type="submit" class="btn primary lg" data-loading-text="{{ __('Accesso...') }}"
            style="width:100%; justify-content:center;">
            {{ __('Accedi') }}
        </button>

        @if (Route::has('password.request'))
            <div class="guest-footer">
                <a href="{{ route('password.request') }}">{{ __('Password dimenticata?') }}</a>
            </div>
        @endif
    </form>
@endsection
