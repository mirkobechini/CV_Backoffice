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

    @if ($telegramConfigured)
        <div class="admin-card" style="margin-bottom:16px;">
            <div class="head">
                <h3>{{ __('Notifiche Telegram') }}</h3>
            </div>
            <div class="body">
                @if (session('status') === 'telegram-link-generated' && session('telegramLinkToken'))
                    <div class="alert warning" style="margin-bottom:14px;">
                        <div style="width:100%;">
                            <strong>{{ __('Apri Telegram, cerca') }} {{ '@' . $telegramBotUsername }} {{ __('e invia:') }}</strong>
                            <p style="margin:6px 0 0; font-family:monospace; font-size:14px;">
                                /start {{ session('telegramLinkToken') }}
                            </p>
                            <p style="margin:6px 0 0;">{{ __('Il codice scade dopo 15 minuti.') }}</p>
                        </div>
                    </div>
                @endif

                @if ($telegramLinked)
                    <p class="hint" style="margin-bottom:14px;">
                        <span class="badge b-green">{{ __('Collegato') }}</span>
                        {{ __('Riceverai le notifiche anche su Telegram, oltre all\'email.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.notifications.telegram-unlink') }}" data-single-submit="true">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn outline">{{ __('Scollega Telegram') }}</button>
                    </form>
                @else
                    <p class="hint" style="margin-bottom:14px;">
                        {{ __('Collega il tuo account Telegram per ricevere qui le stesse notifiche dell\'email.') }}
                    </p>
                    <form method="POST" action="{{ route('admin.notifications.telegram-link') }}" data-single-submit="true">
                        @csrf
                        <button type="submit" class="btn primary">{{ __('Collega Telegram') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

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

            <div class="form-section">
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

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">4</span> {{ __('Tipi di notifica') }}</h2>
                <div class="hint" style="margin:0 0 12px;">
                    {{ __('Scegli per quali eventi vuoi ricevere notifiche in-app ed email (se configurate sopra).') }}
                </div>

                @php
                    $notifyToggles = [
                        'notify_on_deadline' => __('Scadenze'),
                        'notify_on_issue' => __('Guasti'),
                        'notify_on_maintenance' => __('Appuntamenti'),
                        'notify_on_equipment' => __('Attrezzature'),
                        'notify_on_tire_season' => __('Cambio gomme stagionale'),
                    ];
                @endphp

                <div class="row2">
                    @foreach ($notifyToggles as $key => $label)
                        <div>
                            <input type="hidden" name="{{ $key }}" value="0">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" id="{{ $key }}" name="{{ $key }}"
                                    value="1" {{ old($key, $settings[$key] ?? true) ? 'checked' : '' }}>
                                <span class="track"><span class="knob"></span></span>
                                <span class="label">{{ $label }}</span>
                            </label>
                            @error($key)
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
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
