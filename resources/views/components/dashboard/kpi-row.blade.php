@props(['totalVehicles', 'openIssues', 'upcomingDeadlines', 'expiredDeadlines', 'inWorkshopCount', 'expiringEquipment', 'activeInsurancePremiumTotal'])

{{-- 7 KPI: icona/valore/etichetta impilati verticalmente (restyle "Saru", ADR §19) --}}
<div class="dash-kpis">
    <a href="{{ route('admin.vehicles.index') }}" class="dash-kpi">
        <div class="ic k1"><i class="fa-solid fa-truck"></i></div>
        <div class="val">{{ $totalVehicles }}</div>
        <div class="lbl">{{ __('Veicoli totali') }}</div>
    </a>
    <a href="{{ route('admin.issues.index') }}" class="dash-kpi">
        <div class="ic k2"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="val">{{ $openIssues->count() }}</div>
        <div class="lbl">{{ __('Guasti aperti') }}</div>
    </a>
    <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
        <div class="ic k3"><i class="fa-solid fa-clock"></i></div>
        <div class="val">{{ $upcomingDeadlines->count() }}</div>
        <div class="lbl">{{ __('Scadenze imminenti') }}</div>
    </a>
    <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
        <div class="ic k4"><i class="fa-solid fa-calendar-xmark"></i></div>
        <div class="val">{{ $expiredDeadlines->count() }}</div>
        <div class="lbl">{{ __('Scadenze scadute') }}</div>
    </a>
    <a href="{{ route('admin.maintenance-records.index') }}" class="dash-kpi">
        <div class="ic k5"><i class="fa-solid fa-wrench"></i></div>
        <div class="val">{{ $inWorkshopCount }}</div>
        <div class="lbl">{{ __('In officina') }}</div>
    </a>
    <a href="{{ route('admin.equipments.index') }}" class="dash-kpi">
        <div class="ic k6"><i class="fa-solid fa-toolbox"></i></div>
        <div class="val">{{ $expiringEquipment->count() }}</div>
        <div class="lbl">{{ __('Attrez. in scadenza') }}</div>
    </a>
    <a href="{{ route('admin.deadlines.insurances') }}" class="dash-kpi">
        <div class="ic k7"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="val">€ {{ number_format($activeInsurancePremiumTotal, 2, ',', '.') }}</div>
        <div class="lbl">{{ __('Premio assicurativo annuo') }}</div>
    </a>
</div>
