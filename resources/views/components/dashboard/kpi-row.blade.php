@props(['totalVehicles', 'openIssues', 'upcomingDeadlines', 'expiredDeadlines', 'inWorkshopCount', 'expiringEquipment', 'activeInsurancePremiumTotal'])

{{-- 7 KPI: icona/valore/etichetta impilati verticalmente (restyle "Saru", ADR §19).
Sotto i 768px solo i 4 più operativi restano sempre visibili (come nel
mockup); gli altri 3 si aprono con "Vedi tutti gli indicatori" (toggle
JS su una classe propria, non l'attributo `[hidden]`: il preflight di
Tailwind lo resetta con `display:none!important` dentro un suo
`@layer`, che per le dichiarazioni `!important` vince sempre su una
regola non-layered come le nostre, indipendentemente dall'ordine nel
file — `<details>` nativo scartato per lo stesso motivo più un bug di
rendering Chromium separato su `display: contents`). Su desktop il
toggle è nascosto e la seconda riga sempre visibile. --}}
<div class="dash-kpis">
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
</div>
<div class="dash-kpis dash-kpis-extra is-collapsed" id="dash-kpis-extra">
    <a href="{{ route('admin.vehicles.index') }}" class="dash-kpi">
        <div class="ic k1"><i class="fa-solid fa-truck"></i></div>
        <div class="val">{{ $totalVehicles }}</div>
        <div class="lbl">{{ __('Veicoli totali') }}</div>
    </a>
    <a href="{{ route('admin.equipments.index') }}" class="dash-kpi">
        <div class="ic k6"><i class="fa-solid fa-toolbox"></i></div>
        <div class="val">{{ $expiringEquipment->count() }}</div>
        <div class="lbl">{{ __('Attrez. in scadenza') }}</div>
    </a>
    <a href="{{ route('admin.deadlines.insurances') }}" class="dash-kpi">
        <div class="ic k7"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="val val-currency">€ {{ number_format($activeInsurancePremiumTotal, 2, ',', '.') }}</div>
        <div class="lbl">{{ __('Premio assicurativo annuo') }}</div>
    </a>
</div>
<button type="button" class="dash-kpis-toggle" id="dash-kpis-toggle" aria-expanded="false"
    aria-controls="dash-kpis-extra">
    {{ __('Vedi tutti gli indicatori') }} <i class="fa-solid fa-chevron-down"></i>
</button>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('dash-kpis-toggle');
            const extra = document.getElementById('dash-kpis-extra');
            if (!toggle || !extra) return;
            toggle.addEventListener('click', function() {
                const isCollapsed = extra.classList.contains('is-collapsed');
                extra.classList.toggle('is-collapsed', !isCollapsed);
                toggle.setAttribute('aria-expanded', isCollapsed ? 'true' : 'false');
                toggle.querySelector('i').classList.toggle('fa-chevron-down', !isCollapsed);
                toggle.querySelector('i').classList.toggle('fa-chevron-up', isCollapsed);
            });
        });
    </script>
@endpush
