@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Officine'), 'url' => route('admin.providers.index')],
        ['label' => __('Nuova officina')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova struttura') }}</h1>
        <a href="{{ request('back', route('admin.providers.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="provider-form" method="POST" action="{{ route('admin.providers.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli struttura') }}</h2>
                <div class="field">
                    <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" placeholder="{{ __('es. Officina Rossi') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="contact_info">{{ __('Contatti') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('contact_info') is-invalid @enderror" id="contact_info"
                        name="contact_info" value="{{ old('contact_info') }}"
                        placeholder="{{ __('es. +39 333 123 4567 · rossi@officina.it') }}" required>
                    @error('contact_info')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="address">{{ __('Indirizzo') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('address') is-invalid @enderror" id="address" name="address"
                        value="{{ old('address') }}" placeholder="{{ __('es. Via Roma 12, Milano') }}" required>
                    @error('address')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="type">{{ __('Tipo') }} <span class="req">*</span></label>
                    <select class="select @error('type') is-invalid @enderror" id="type" name="type" required>
                        <option value="" disabled selected>{{ __('Seleziona tipo...') }}</option>
                        @foreach (['Meccanico', 'Carrozziere', 'Gommista', 'Lavaggio', 'Allestitore', 'Vetri', 'Elettrauto', 'Centro Revisioni'] as $tipo)
                            <option value="{{ $tipo }}" {{ old('type') == $tipo ? 'selected' : '' }}>
                                {{ $tipo }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="provider-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Aggiungi struttura') }}
                </button>
                <a href="{{ request('back', route('admin.providers.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
