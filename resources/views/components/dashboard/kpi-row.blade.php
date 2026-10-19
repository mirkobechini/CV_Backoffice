@props(['totalVehicles', 'openIssues', 'upcomingDeadlines', 'expiredDeadlines', 'inWorkshopCount', 'expiringEquipment', 'activeInsurancePremiumTotal'])

{{-- 6 KPI --}}
<div class="dash-kpis">
    <a href="{{ route('admin.vehicles.index') }}" class="dash-kpi">
        <div class="ic k1"><i class="fa-solid fa-truck"></i></div>
        <div>
            <div class="lbl">{{ __('Veicoli totali') }}</div>
            <div class="val">{{ $totalVehicles }}</div>
        </div>
    </a>
    <a href="{{ route('admin.issues.index') }}" class="dash-kpi">
        <div class="ic k2"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="lbl">{{ __('Guasti aperti') }}</div>
            <div class="val">{{ $openIssues->count() }}</div>
        </div>
    </a>
    <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
        <div class="ic k3"><i class="fa-solid fa-clock"></i></div>
        <div>
            <div class="lbl">{{ __('Scadenze imminenti') }}</div>
            <div class="val">{{ $upcomingDeadlines->count() }}</div>
        </div>
    </a>
    <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
        <div class="ic k4"><i class="fa-solid fa-calendar-xmark"></i></div>
        <div>
            <div class="lbl">{{ __('Scadenze scadute') }}</div>
            <div class="val">{{ $expiredDeadlines->count() }}</div>
        </div>
    </a>
    <a href="{{ route('admin.maintenance-records.index') }}" class="dash-kpi">
        <div class="ic k5"><i class="fa-solid fa-wrench"></i></div>
        <div>
            <div class="lbl">{{ __('In officina') }}</div>
            <div class="val">{{ $inWorkshopCount }}</div>
        </div>
    </a>
    <a href="{{ route('admin.equipments.index') }}" class="dash-kpi">
        <div class="ic k6"><i class="fa-solid fa-toolbox"></i></div>
        <div>
            <div class="lbl">{{ __('Attrez. in scadenza') }}</div>
            <div class="val">{{ $expiringEquipment->count() }}</div>
        </div>
    </a>
    <a href="{{ route('admin.deadlines.insurances') }}" class="dash-kpi">
        <div class="ic k7"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <div class="lbl">{{ __('Premio assicurativo annuo') }}</div>
            <div class="val">€ {{ number_format($activeInsurancePremiumTotal, 2, ',', '.') }}</div>
        </div>
    </a>
</div>
