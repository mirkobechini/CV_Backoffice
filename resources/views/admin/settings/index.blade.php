@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Impostazioni')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Impostazioni') }}</h1>
    </div>

    <div class="row2">
        <div class="admin-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--primary-soft); color:var(--primary);"><i
                            class="fa-solid fa-user"></i></span> {{ __('Account') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">
                    {{ __('Nome, email, password e altre impostazioni personali del tuo account.') }}</p>
                <a href="{{ route('profile.edit') }}" class="btn outline">
                    <i class="fa-solid fa-arrow-right"></i> {{ __('Vai al tuo account') }}
                </a>
                <p class="hint" style="margin-top:14px;">
                    {{ __('Per rinominare un gruppo, invitare membri o gestirne i ruoli, apri la pagina del gruppo da') }}
                    <a href="{{ route('admin.groups.index') }}">{{ __('Gruppi') }}</a>.
                </p>
            </div>
        </div>

        @if ($canManageBackups)
            <div class="admin-card">
                <div class="head">
                    <h3><span class="ic" style="background:var(--green-soft); color:var(--green);"><i
                                class="fa-solid fa-circle-dot"></i></span> {{ __('Cambio gomme stagionale') }}</h3>
                </div>
                <div class="body">
                    <p class="hint" style="margin-bottom:10px;">
                        {{ __('Date (uguali per tutta la flotta) entro cui i veicoli dovrebbero passare a gomme invernali o estive. Usate dal promemoria e dal riquadro in dashboard.') }}
                    </p>
                    @php
                        [$winterMonth, $winterDay] = explode('-', $fleetSettings->winter_switch_date);
                        [$summerMonth, $summerDay] = explode('-', $fleetSettings->summer_switch_date);
                        $months = [
                            1 => __('Gennaio'), 2 => __('Febbraio'), 3 => __('Marzo'), 4 => __('Aprile'),
                            5 => __('Maggio'), 6 => __('Giugno'), 7 => __('Luglio'), 8 => __('Agosto'),
                            9 => __('Settembre'), 10 => __('Ottobre'), 11 => __('Novembre'), 12 => __('Dicembre'),
                        ];
                    @endphp
                    <form method="POST" action="{{ route('admin.settings.tire-season.update') }}"
                        data-single-submit="true">
                        @csrf
                        @method('PATCH')
                        <div class="row2">
                            <div class="field">
                                <label>{{ __('Passaggio a invernali') }}</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="number" class="input @error('winter_switch_day') is-invalid @enderror"
                                        name="winter_switch_day" min="1" max="31"
                                        value="{{ old('winter_switch_day', (int) $winterDay) }}" style="width:80px;">
                                    <select class="select" name="winter_switch_month">
                                        @foreach ($months as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('winter_switch_month', (int) $winterMonth) === $num ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('winter_switch_day')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label>{{ __('Passaggio a estive') }}</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="number" class="input @error('summer_switch_day') is-invalid @enderror"
                                        name="summer_switch_day" min="1" max="31"
                                        value="{{ old('summer_switch_day', (int) $summerDay) }}" style="width:80px;">
                                    <select class="select" name="summer_switch_month">
                                        @foreach ($months as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('summer_switch_month', (int) $summerMonth) === $num ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('summer_switch_day')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn outline" data-loading-text="{{ __('Salvataggio...') }}">
                            <i class="fa-solid fa-check"></i> {{ __('Salva date') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <div class="head">
                    <h3><span class="ic" style="background:var(--blue-soft); color:var(--blue);"><i
                                class="fa-solid fa-database"></i></span> {{ __('Backup database') }}</h3>
                </div>
                <div class="body">
                    <p class="hint" style="margin-bottom:10px;">
                        {{ __('Crea un backup del database in formato JSON.') }}</p>
                    <form method="POST" action="{{ route('admin.settings.backup') }}" data-single-submit="true">
                        @csrf
                        <button type="submit" class="btn outline" data-loading-text="{{ __('Creazione...') }}">
                            <i class="fa-solid fa-floppy-disk"></i> {{ __('Crea backup') }}
                        </button>
                    </form>

                    @if ($backups->isNotEmpty())
                        <div class="backup-list">
                            <h4>{{ __('Backup recenti') }}</h4>
                            @foreach ($backups as $backup)
                                <div class="b-item">
                                    <span class="ic"><i class="fa-solid fa-file-lines"></i></span>
                                    <span class="name">{{ $backup['name'] }}</span>
                                    <span class="size">{{ round($backup['size'] / 1024, 1) }} KB</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
