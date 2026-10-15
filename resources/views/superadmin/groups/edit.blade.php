@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Associazioni'), 'url' => route('superadmin.dashboard')],
        ['label' => $group->name],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica associazione') }}</h1>
        <a href="{{ route('superadmin.dashboard') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.groups.update', $group) }}" data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <div class="field" style="margin-bottom:0;">
                    <label for="name">{{ __('Nome associazione') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name', $group->name) }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva') }}
                </button>
                <a href="{{ route('superadmin.dashboard') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    @if ($capo)
        <div class="form-card" style="margin-top:20px;">
            <h2 style="margin-top:0;">{{ __('Capo') }}</h2>
            <div class="hint" style="margin:0 0 12px;">
                {{ __("Correggi qui nome/email se sbagliati in fase di creazione: senza un'email corretta il capo non riceve mai il link per impostare la password e resta bloccato fuori.") }}
            </div>
            <form method="POST" action="{{ route('superadmin.groups.capo.update', $group) }}" data-single-submit="true">
                @csrf
                @method('PUT')

                <div class="row2" style="margin-bottom:0;">
                    <div class="field">
                        <label for="capo_name">{{ __('Nome') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('capo_name') is-invalid @enderror" id="capo_name"
                            name="capo_name" value="{{ old('capo_name', $capo->name) }}" required>
                        @error('capo_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="capo_email">{{ __('Email') }} <span class="req">*</span></label>
                        <input type="email" class="input @error('capo_email') is-invalid @enderror" id="capo_email"
                            name="capo_email" value="{{ old('capo_email', $capo->email) }}" required>
                        @error('capo_email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio...') }}">
                        {{ __('Salva dati capo') }}
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('superadmin.groups.capo.resend-password-link', $group) }}">
                @csrf
                <button type="submit" class="btn">
                    <i class="fa-solid fa-paper-plane"></i> {{ __('Invia di nuovo l\'email per impostare la password') }}
                </button>
            </form>
        </div>
    @endif
@endsection
