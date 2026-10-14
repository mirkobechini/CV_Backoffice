@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Codici sconto'), 'url' => route('superadmin.discount-codes.index')],
        ['label' => __('Nuovo codice')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Nuovo codice sconto') }}</h1>
        <a href="{{ route('superadmin.discount-codes.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.discount-codes.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <div class="row2">
                    <div class="field">
                        <label for="code">{{ __('Codice') }}</label>
                        <input type="text" class="input @error('code') is-invalid @enderror" id="code" name="code"
                            value="{{ old('code') }}" style="text-transform:uppercase;"
                            placeholder="{{ __('vuoto = generato automaticamente') }}">
                        @error('code')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="percent">{{ __('Percentuale sconto') }} <span class="req">*</span></label>
                        <input type="number" min="1" max="100" class="input @error('percent') is-invalid @enderror"
                            id="percent" name="percent" value="{{ old('percent') }}" required>
                        @error('percent')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="max_uses">{{ __('Numero massimo di utilizzi') }}</label>
                        <input type="number" min="1" class="input @error('max_uses') is-invalid @enderror"
                            id="max_uses" name="max_uses" value="{{ old('max_uses') }}"
                            placeholder="{{ __('vuoto = illimitato') }}">
                        @error('max_uses')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="expires_at">{{ __('Scadenza') }}</label>
                        <input type="date" class="input @error('expires_at') is-invalid @enderror" id="expires_at"
                            name="expires_at" value="{{ old('expires_at') }}" placeholder="{{ __('vuoto = senza scadenza') }}">
                        @error('expires_at')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Crea codice') }}
                </button>
                <a href="{{ route('superadmin.discount-codes.index') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
