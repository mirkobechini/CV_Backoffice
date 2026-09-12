@extends('layouts.guest')

@section('content')
    <h1 class="guest-title">{{ __('Conferma password') }}</h1>
    <p class="hint" style="margin-bottom:20px;">
        {{ __('Per proseguire, conferma la tua password prima di continuare.') }}</p>

    <form method="POST" action="{{ route('password.confirm') }}" data-single-submit="true">
        @csrf

        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input id="password" type="password" class="input @error('password') is-invalid @enderror"
                name="password" required autocomplete="current-password" autofocus>
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn primary lg" data-loading-text="{{ __('Conferma...') }}"
            style="width:100%; justify-content:center;">
            {{ __('Conferma') }}
        </button>

        @if (Route::has('password.request'))
            <div class="guest-footer">
                <a href="{{ route('password.request') }}">{{ __('Password dimenticata?') }}</a>
            </div>
        @endif
    </form>
@endsection
