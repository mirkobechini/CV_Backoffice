@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Password dimenticata') }}</h1>
    <p class="hint" style="margin-bottom:20px;">
        {{ __('Inserisci il tuo indirizzo email: ti invieremo un link per reimpostare la password.') }}
    </p>

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" data-single-submit="true">
        @csrf

        <div class="field">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" type="email" class="input @error('email') is-invalid @enderror" name="email"
                value="{{ old('email') }}" required autocomplete="email" autofocus>
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn primary lg" data-loading-text="{{ __('Invio...') }}"
            style="width:100%; justify-content:center;">
            {{ __('Invia link di reset') }}
        </button>

        <div class="guest-footer">
            <a href="{{ route('login') }}">{{ __('Torna al login') }}</a>
        </div>
    </form>
@endsection
