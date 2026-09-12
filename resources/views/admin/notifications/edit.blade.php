@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Notifiche')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Impostazioni notifiche') }}</h1>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.notifications.update') }}" data-single-submit="true">
            @csrf
            @method('PATCH')

            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Email destinatario') }}</h2>
                <div class="field">
                    <label for="report_email">{{ __('Indirizzo email per ricevere i report') }} <span
                            class="req">*</span></label>
                    <input type="email" class="input @error('report_email') is-invalid @enderror" id="report_email"
                        name="report_email" value="{{ old('report_email', $settings['report_email'] ?? '') }}" required>
                    @error('report_email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-section">
                <h2><span class="num">2</span> {{ __('Frequenza report') }}</h2>
                <div class="field">
                    <label for="report_frequency">{{ __('Ogni quanto ricevere il report riassuntivo') }} <span
                            class="req">*</span></label>
                    <select class="select @error('report_frequency') is-invalid @enderror" id="report_frequency"
                        name="report_frequency" required>
                        <option value="daily" @selected(old('report_frequency', $settings['report_frequency'] ?? '') === 'daily')>
                            {{ __('Ogni giorno') }}</option>
                        <option value="weekly" @selected(old('report_frequency', $settings['report_frequency'] ?? '') === 'weekly')>
                            {{ __('Ogni settimana (lunedì)') }}</option>
                        <option value="monthly" @selected(old('report_frequency', $settings['report_frequency'] ?? '') === 'monthly')>
                            {{ __('Ogni mese (1° giorno)') }}</option>
                    </select>
                    @error('report_frequency')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">3</span> {{ __('Promemoria') }}</h2>
                <div class="field" style="margin-bottom:0;">
                    <label for="reminder_days_before">{{ __('Quanti giorni prima avvisare per le scadenze') }} <span
                            class="req">*</span></label>
                    <input type="number" class="input @error('reminder_days_before') is-invalid @enderror"
                        id="reminder_days_before" name="reminder_days_before"
                        value="{{ old('reminder_days_before', $settings['reminder_days_before'] ?? '7') }}" min="1"
                        max="90" required>
                    @error('reminder_days_before')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <div class="hint">
                        {{ __('Le scadenze con data entro questo numero di giorni verranno incluse nel report come "in arrivo".') }}
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva impostazioni') }}
                </button>
            </div>
        </form>
    </div>
@endsection
