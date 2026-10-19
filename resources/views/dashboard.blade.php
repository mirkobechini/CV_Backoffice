@extends('layouts.app')

@section('content')

    {{-- Header: titolo + data odierna --}}
    <div class="page-header">
        <h1><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</h1>
        <div class="date">{{ __('Oggi,') }} {{ now()->translatedFormat('l j F Y') }}</div>
    </div>

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

    <x-dashboard.deadlines-row :upcoming-deadlines="$upcomingDeadlines" :expired-deadlines="$expiredDeadlines" />

    <x-dashboard.workshop-row :in-workshop-records="$inWorkshopRecords" :upcoming-appointments="$upcomingAppointments" />

    <x-dashboard.open-issues-card :open-issues="$openIssues" />

    <x-dashboard.equipment-row :total-vehicles="$totalVehicles" :incomplete-vehicles="$incompleteVehicles"
        :expiring-equipment="$expiringEquipment" />

    <x-dashboard.tire-season-carwash-row :tire-season-expected="$tireSeasonExpected"
        :tire-season-next-switch="$tireSeasonNextSwitch" :tire-season-compliant-count="$tireSeasonCompliantCount"
        :tire-season-pending="$tireSeasonPending" :tire-season-overdue="$tireSeasonOverdue"
        :car-wash-total="$carWashTotal" :car-wash-washed-count="$carWashWashedCount"
        :car-wash-missing-vehicles="$carWashMissingVehicles" :car-wash-records="$carWashRecords" />
@endsection
