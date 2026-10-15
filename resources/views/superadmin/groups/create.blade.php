@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Associazioni'), 'url' => route('superadmin.dashboard')],
        ['label' => __('Nuova associazione')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Nuova associazione') }}</h1>
        <a href="{{ route('superadmin.dashboard') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.groups.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Associazione') }}</h2>
                <div class="field" style="margin-bottom:0;">
                    <label for="name">{{ __('Nome associazione') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" placeholder="{{ __('es. Croce Verde Esempio') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">2</span> {{ __('Primo capo') }}</h2>
                <div class="hint" style="margin:0 0 12px;">
                    {{ __("L'account creato qui diventa capo del nuovo gruppo; potrà poi creare sottocapi/membri da solo.") }}
                </div>
                <div class="field">
                    <label for="capo_name">{{ __('Nome') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('capo_name') is-invalid @enderror" id="capo_name"
                        name="capo_name" value="{{ old('capo_name') }}" placeholder="{{ __('es. Luca Verdi') }}" required>
                    @error('capo_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="capo_email">{{ __('Email') }} <span class="req">*</span></label>
                    <input type="email" class="input @error('capo_email') is-invalid @enderror" id="capo_email"
                        name="capo_email" value="{{ old('capo_email') }}" placeholder="{{ __('es. luca@example.com') }}" required>
                    @error('capo_email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2" style="margin-bottom:0;">
                    <div class="field password-field">
                        <label for="capo_password">{{ __('Password') }} <span class="req">*</span></label>
                        <input type="password" class="input @error('capo_password') is-invalid @enderror"
                            id="capo_password" name="capo_password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" aria-label="{{ __('Mostra password') }}">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        @error('capo_password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field password-field">
                        <label for="capo_password_confirmation">{{ __('Conferma password') }} <span class="req">*</span></label>
                        <input type="password" class="input" id="capo_password_confirmation"
                            name="capo_password_confirmation" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" aria-label="{{ __('Mostra password') }}">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Creazione...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Crea associazione') }}
                </button>
                <a href="{{ route('superadmin.dashboard') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
