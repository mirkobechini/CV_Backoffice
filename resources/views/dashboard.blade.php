@extends('layouts.app')

@php
    // Saluto in base all'ora del giorno + nome di battesimo (restyle "Saru",
    // ADR §19), ispirato al mockup validato con Mirko.
    $greetingHour = now()->hour;
    $greeting = match (true) {
        $greetingHour < 12 => __('Buongiorno'),
        $greetingHour < 18 => __('Buon pomeriggio'),
        default => __('Buonasera'),
    };
    $firstName = explode(' ', trim(auth()->user()->name))[0] ?? auth()->user()->name;

    $attentionCount = $expiredDeadlines->count() + $staleVehiclesCount;
@endphp

@section('content')

    {{-- Header: saluto + data odierna + azione rapida --}}
    <div class="page-header">
        <div>
            <div class="date">{{ __('Oggi,') }} {{ now()->translatedFormat('l j F Y') }}</div>
            <h1>{{ $greeting }}, {{ $firstName }} 👋</h1>
        </div>
        <div class="dropdown">
            <button type="button" class="btn primary" data-bs-toggle="dropdown" data-bs-auto-close="true"
                aria-expanded="false">
                <i class="fa-solid fa-plus"></i> {{ __('Nuovo') }}
                <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('admin.vehicles.create') }}"
                        style="display:flex;align-items:center;gap:10px;">
                        <span class="dropdown-item-icon" style="background:var(--primary-soft);color:var(--primary);"><i
                                class="fa-solid fa-truck"></i></span>
                        {{ __('Nuovo veicolo') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.issues.create') }}"
                        style="display:flex;align-items:center;gap:10px;">
                        <span class="dropdown-item-icon" style="background:var(--red-soft);color:var(--red);"><i
                                class="fa-solid fa-triangle-exclamation"></i></span>
                        {{ __('Nuovo guasto') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.fuel-logs.create') }}"
                        style="display:flex;align-items:center;gap:10px;">
                        <span class="dropdown-item-icon" style="background:var(--blue-soft);color:var(--blue);"><i
                                class="fa-solid fa-gas-pump"></i></span>
                        {{ __('Nuovo rifornimento') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.maintenance-records.create') }}"
                        style="display:flex;align-items:center;gap:10px;">
                        <span class="dropdown-item-icon" style="background:var(--amber-soft);color:var(--amber);"><i
                                class="fa-solid fa-calendar-check"></i></span>
                        {{ __('Nuovo appuntamento') }}</a></li>
            </ul>
        </div>
    </div>

    @if ($attentionCount > 0)
        <div class="dash-attention-banner">
            <div class="ic"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="body">
                <div class="title">
                    {{ trans_choice(':count cosa richiede attenzione oggi|:count cose richiedono attenzione oggi', $attentionCount, ['count' => $attentionCount]) }}
                </div>
                <div class="sub">
                    @if ($expiredDeadlines->count() > 0 && $staleVehiclesCount > 0)
                        {{ trans_choice(':count scadenza già scaduta|:count scadenze già scadute', $expiredDeadlines->count(), ['count' => $expiredDeadlines->count()]) }},
                        {{ trans_choice(':count veicolo fermo da tempo senza registrazioni|:count veicoli fermi da tempo senza registrazioni', $staleVehiclesCount, ['count' => $staleVehiclesCount]) }}
                    @elseif ($expiredDeadlines->count() > 0)
                        {{ trans_choice(':count scadenza già scaduta|:count scadenze già scadute', $expiredDeadlines->count(), ['count' => $expiredDeadlines->count()]) }}
                    @else
                        {{ trans_choice(':count veicolo fermo da tempo senza registrazioni|:count veicoli fermi da tempo senza registrazioni', $staleVehiclesCount, ['count' => $staleVehiclesCount]) }}
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.deadlines.index') }}" class="btn outline-solid">{{ __('Vedi tutto') }}</a>
        </div>
    @endif

    @if (auth()->user()->isManager() && ! auth()->user()->hasTwoFactorEnabled())
        <div class="alert warning" style="margin-bottom:16px;">
            <div style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <div>
                    <strong>{{ __('Consigliato: attiva il 2FA') }}</strong>
                    <p style="margin:4px 0 0;">
                        {{ __('Il tuo ruolo ha accesso ampio ai dati della flotta: proteggi il tuo account con un secondo fattore.') }}
                    </p>
                </div>
                <a href="{{ route('profile.edit') }}" class="btn outline-solid">{{ __('Vai alle impostazioni') }}</a>
            </div>
        </div>
    @endif

    <x-dashboard.kpi-row :total-vehicles="$totalVehicles" :open-issues="$openIssues"
        :upcoming-deadlines="$upcomingDeadlines" :expired-deadlines="$expiredDeadlines"
        :in-workshop-count="$inWorkshopCount" :expiring-equipment="$expiringEquipment"
        :active-insurance-premium-total="$activeInsurancePremiumTotal" />

    {{-- 3 colonne fisse come nel mockup (restyle "Saru", ADR §19), al posto
         delle righe impilate a coppie — 3 card per colonna, raggruppate per
         urgenza invece che per entità, così le colonne restano equilibrate
         in altezza: col.1 richiede attenzione ora, col.2 in arrivo a breve,
         col.3 stato generale/completezza. --}}
    <div class="dash-columns">
        <div class="dash-col">
            <x-dashboard.open-issues-card :open-issues="$openIssues" />
            <x-dashboard.in-workshop-card :in-workshop-records="$inWorkshopRecords" />
            <x-dashboard.expired-deadlines-card :expired-deadlines="$expiredDeadlines" />
        </div>
        <div class="dash-col">
            <x-dashboard.upcoming-appointments-card :upcoming-appointments="$upcomingAppointments" />
            <x-dashboard.upcoming-deadlines-card :upcoming-deadlines="$upcomingDeadlines" />
            <x-dashboard.expiring-equipment-card :expiring-equipment="$expiringEquipment" />
        </div>
        <div class="dash-col">
            <x-dashboard.equipment-completeness-card :total-vehicles="$totalVehicles"
                :incomplete-vehicles="$incompleteVehicles" />
            <x-dashboard.tire-season-card :tire-season-expected="$tireSeasonExpected"
                :tire-season-next-switch="$tireSeasonNextSwitch" :tire-season-compliant-count="$tireSeasonCompliantCount"
                :tire-season-pending="$tireSeasonPending" :tire-season-overdue="$tireSeasonOverdue" />
            <x-dashboard.car-wash-card :car-wash-total="$carWashTotal" :car-wash-washed-count="$carWashWashedCount"
                :car-wash-missing-vehicles="$carWashMissingVehicles" :car-wash-records="$carWashRecords" />
        </div>
    </div>
@endsection
