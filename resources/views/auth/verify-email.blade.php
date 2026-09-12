@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Verifica il tuo indirizzo email') }}</h1>

    @if (session('resent'))
        <div class="alert success" style="margin-top:14px;">
            {{ __('Una nuova mail di verifica è stata inviata al tuo indirizzo email.') }}
        </div>
    @endif

    <p class="hint" style="margin:14px 0;">
        {{ __('Prima di continuare, controlla la tua email per un link di verifica.') }}
        {{ __('Se non hai ricevuto la mail,') }}
    </p>

    <form method="POST" action="{{ route('verification.send') }}" data-single-submit="true">
        @csrf
        <button type="submit" class="btn primary" data-loading-text="{{ __('Invio...') }}">
            {{ __('Invia di nuovo la mail di verifica') }}
        </button>
    </form>

    <div class="guest-footer">
        <form method="POST" action="{{ route('logout') }}" data-single-submit="true">
            @csrf
            <button type="submit" class="btn ghost sm">{{ __('Esci') }}</button>
        </form>
    </div>
@endsection
