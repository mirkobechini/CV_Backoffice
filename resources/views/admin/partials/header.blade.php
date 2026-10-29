{{--
    Riorganizzata da 5 sezioni con 22 voci piatte a gruppi per flusso di
    lavoro (restyle "Saru", ADR §19): "Servizi" diventa "Manutenzione" con
    badge sul numero di guasti aperti/scadenze imminenti. Allineata al
    mockup validato con Mirko: Carburante/Pedaggi/Tessere/Km/Tipi e le 4
    voci Attrezzature sono consolidate ciascuna in un sottomenu a tendina
    invece di linee indipendenti; ricerca rapida veicoli (sigla/targa) in
    cima; stato attivo a sfondo pieno invece che bordo sinistro. Nessuna
    voce di navigazione rimossa rispetto a prima, solo raggruppata.
--}}
@php
    $sidebarOpenIssuesCount = \App\Models\Issue::forCurrentUser()->open()->count();
    // Scadute o in arrivo nei prossimi 30 giorni: stesso orizzonte di
    // Deadline::scopeUpcoming(), solo un conteggio SQL leggero (nessun
    // calcolo automatic_status per km) — è un indicatore di navigazione,
    // non una statistica esatta.
    $sidebarUpcomingDeadlinesCount = \App\Models\Deadline::forCurrentUser()
        ->where('is_renewed', false)
        ->where('due_date', '<=', now()->addDays(30))
        ->count();

    $fuelGroupActive = request()->routeIs([
        'admin.fuel-logs.*',
        'admin.fleet-devices.*',
        'admin.fuel-cards.*',
        'admin.mileage-logs.*',
        'admin.vehicle-types.*',
    ]);
    $equipmentGroupActive = request()->routeIs([
        'admin.equipments.*',
        'admin.equipment-types.*',
        'admin.equipment-issues.*',
        'admin.equipment-maintenance-records.*',
    ]);
@endphp

<aside class="sidebar-dp">
    {{-- Brand --}}
    <a class="sidebar-brand" href="{{ route('dashboard') }}">
        <span class="sidebar-logo">
            <svg viewBox="0 0 377.83 376.2" width="26" height="26" fill="none" aria-hidden="true">
                <path fill="#e2792d" d="M154.51,139.94c18.01-.08,23.11,12.1,23.11,12.1,13.79-8.68,22.55,0,22.55,0,3.17-12.33,22.11-12.1,22.11-12.1h64.55c1.82.08,3.39.37,4.85.75-11.45-18.78-32.06-31.36-55.67-31.36-18.53,0-35.22,7.77-47.09,20.18-11.87-12.41-28.56-20.18-47.09-20.18-23.61,0-44.23,12.59-55.68,31.39,1.93-.58,3.33-.77,3.33-.77,0,0,47.02.08,65.03,0Z" />
                <path stroke="#e2792d" stroke-width="11" stroke-miterlimit="10" fill="none" d="M312.35,139.31s23.06-15.98,39.63-3.74c25.59,18.89,37.13,83.96-27.59,105.81,0,0,11.78,55.83-81.97,114.16-54.83,34.11-107.02,0-107.02,0-96.63-53.43-81.97-114.16-81.97-114.16C-11.28,219.52.25,154.45,25.84,135.56c16.57-12.24,39.63,3.74,39.63,3.74,0,0,.44-74.8,64.69-108.77,0,0-4.6,10.33-2.23,24.98,0,0,38.13-54.94,104.1-49.67,0,0-15.24,12.05-16.56,23.08,0,0,32.22-11.46,49.07-2.51,0,0-19.24,8.92-18.23,19.76,0,0,59.81,22.38,66.03,93.12Z" />
                <path fill="#e2792d" d="M155.58,150.62c-11.77,0-64.19,0-64.19,0,0,0-6.22-.36-6.69,5.74-.48,6.1,6.46,49.13,6.46,49.13,0,0,1.26,7.53,17.57,7.89h36.22s17.15-10.22,20.32-16.38c3.17-6.16,4.18-37.65,4.18-37.65,0,0-2.09-8.73-13.87-8.73Z" />
                <path fill="#e2792d" d="M293.03,156.32c-.48-6.1-6.69-5.74-6.69-5.74,0,0-52.42,0-64.19,0-11.77,0-13.87,8.73-13.87,8.73,0,0,1.02,31.5,4.18,37.65,3.17,6.16,20.32,16.38,20.32,16.38h36.22c16.32-.36,17.57-7.89,17.57-7.89,0,0,6.93-43.03,6.46-49.13Z" />
                <path fill="#e2792d" d="M279.95,222.66c-5.43,1.37-9.98,1.12-9.98,1.12l-40.28.12s-20.74-9.92-26.91-21.76c-6.17-11.83-5.25-43.38-5.25-43.38-9.26-7.28-17.57-.02-17.57-.02,0,0,.78,31.68-5.18,43.75-5.95,12.07-26.98,21.28-26.98,21.28,0,0-19.54,1.49-42.08,0-2.94-.19-5.51-.59-7.8-1.11,9.61,8.75,21.82,14.7,35.36,16.47-4.75,8.69-7.45,18.65-7.45,29.25,0,33.75,27.36,61.1,61.1,61.1s61.1-27.36,61.1-61.1c0-10.44-2.63-20.27-7.25-28.87,15.05-1.1,28.66-7.3,39.16-16.87Z" />
            </svg>
        </span>
        <span class="brand-text">{{ config('app.name', 'Gestione Flotta') }}</span>
        <span class="badge text-bg-secondary ms-auto small" style="font-size: 0.6rem;">{{ config('app.version') }}</span>
    </a>

    {{-- Ricerca rapida veicoli (sigla/targa), ADR §19 --}}
    <div class="sidebar-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" id="sidebar-vehicle-search" autocomplete="off"
            placeholder="{{ __('Cerca veicolo…') }}" aria-label="{{ __('Cerca veicolo per sigla o targa') }}"
            data-suggestions-url="{{ route('api.vehicles.suggestions') }}"
            data-vehicle-url-base="{{ url('admin/vehicles') }}">
        <div class="sidebar-search-results" id="sidebar-vehicle-search-results"></div>
    </div>

    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"
            title="{{ __('Dashboard') }}">
            <i class="fa-solid fa-gauge-high"></i><span>{{ __('Dashboard') }}</span>
        </a>
    </nav>

    {{-- Sezione: Flotta --}}
    <div class="sidebar-section">{{ __('Flotta') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('admin.vehicles.*') ? 'active' : '' }}"
            href="{{ route('admin.vehicles.index') }}" title="{{ __('Veicoli') }}">
            <i class="fa-solid fa-truck"></i><span>{{ __('Veicoli') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.tires.*') ? 'active' : '' }}"
            href="{{ route('admin.tires.index') }}" title="{{ __('Pneumatici') }}">
            <i class="fa-solid fa-circle-dot"></i><span>{{ __('Pneumatici') }}</span>
        </a>
        <button type="button"
            class="sidebar-link sidebar-link-toggle {{ $fuelGroupActive ? 'active open' : '' }}"
            data-sidebar-submenu-toggle="fuel-group">
            <i class="fa-solid fa-gas-pump"></i><span>{{ __('Carburante, Pedaggi, Tessere') }}</span>
            <i class="fa-solid fa-chevron-down sidebar-submenu-caret"></i>
        </button>
        <div class="sidebar-submenu {{ $fuelGroupActive ? 'open' : '' }}" id="fuel-group">
            <a class="sidebar-link {{ request()->routeIs('admin.fuel-logs.*') ? 'active' : '' }}"
                href="{{ route('admin.fuel-logs.index') }}" title="{{ __('Carburante') }}">
                <i class="fa-solid fa-gas-pump"></i><span>{{ __('Carburante') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.fleet-devices.*') ? 'active' : '' }}"
                href="{{ route('admin.fleet-devices.index') }}" title="{{ __('Dispositivi pedaggio') }}">
                <i class="fa-solid fa-road-barrier"></i><span>{{ __('Pedaggi') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.fuel-cards.*') ? 'active' : '' }}"
                href="{{ route('admin.fuel-cards.index') }}" title="{{ __('Tessere carburante') }}">
                <i class="fa-solid fa-credit-card"></i><span>{{ __('Tessere') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.mileage-logs.*') ? 'active' : '' }}"
                href="{{ route('admin.mileage-logs.index') }}" title="{{ __('Chilometraggi') }}">
                <i class="fa-solid fa-road"></i><span>{{ __('Km') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.vehicle-types.*') ? 'active' : '' }}"
                href="{{ route('admin.vehicle-types.index') }}" title="{{ __('Tipi di veicoli') }}">
                <i class="fa-solid fa-tags"></i><span>{{ __('Tipi') }}</span>
            </a>
        </div>
    </nav>

    {{-- Sezione: Manutenzione (prima "Servizi") --}}
    <div class="sidebar-section">{{ __('Manutenzione') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('admin.providers.*') ? 'active' : '' }}"
            href="{{ route('admin.providers.index') }}" title="{{ __('Officine') }}">
            <i class="fa-solid fa-building"></i><span>{{ __('Officine') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.issues.*') ? 'active' : '' }}"
            href="{{ route('admin.issues.index') }}" title="{{ __('Guasti') }}">
            <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('Guasti') }}</span>
            @if ($sidebarOpenIssuesCount > 0)
                <span class="sidebar-badge sidebar-badge-danger">{{ $sidebarOpenIssuesCount }}</span>
            @endif
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.maintenance-records.*') ? 'active' : '' }}"
            href="{{ route('admin.maintenance-records.index') }}" title="{{ __('Appuntamenti') }}">
            <i class="fa-solid fa-calendar-check"></i><span>{{ __('Appuntamenti') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.deadlines.*') ? 'active' : '' }}"
            href="{{ route('admin.deadlines.index') }}" title="{{ __('Scadenze') }}">
            <i class="fa-solid fa-clock"></i><span>{{ __('Scadenze') }}</span>
            @if ($sidebarUpcomingDeadlinesCount > 0)
                <span class="sidebar-badge sidebar-badge-amber">{{ $sidebarUpcomingDeadlinesCount }}</span>
            @endif
        </a>
    </nav>

    {{-- Sezione: Attrezzature --}}
    <div class="sidebar-section">{{ __('Attrezzature') }}</div>
    <nav class="sidebar-nav">
        <button type="button"
            class="sidebar-link sidebar-link-toggle {{ $equipmentGroupActive ? 'active open' : '' }}"
            data-sidebar-submenu-toggle="equipment-group">
            <i class="fa-solid fa-toolbox"></i><span>{{ __('Attrezzature e guasti') }}</span>
            <i class="fa-solid fa-chevron-down sidebar-submenu-caret"></i>
        </button>
        <div class="sidebar-submenu {{ $equipmentGroupActive ? 'open' : '' }}" id="equipment-group">
            <a class="sidebar-link {{ request()->routeIs('admin.equipments.*') ? 'active' : '' }}"
                href="{{ route('admin.equipments.index') }}" title="{{ __('Attrezzature') }}">
                <i class="fa-solid fa-toolbox"></i><span>{{ __('Attrezzature') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.equipment-types.*') ? 'active' : '' }}"
                href="{{ route('admin.equipment-types.index') }}" title="{{ __('Tipi di Attrezzature') }}">
                <i class="fa-solid fa-tag"></i><span>{{ __('Tipi Attrezzature') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.equipment-issues.*') ? 'active' : '' }}"
                href="{{ route('admin.equipment-issues.index') }}" title="{{ __('Guasti Attrezzature') }}">
                <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('Guasti Attrezzature') }}</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('admin.equipment-maintenance-records.*') ? 'active' : '' }}"
                href="{{ route('admin.equipment-maintenance-records.index') }}"
                title="{{ __('Appuntamenti Attrezzature') }}">
                <i class="fa-solid fa-calendar-check"></i><span>{{ __('Appuntamenti Attrezzature') }}</span>
            </a>
        </div>
    </nav>

    {{-- Sezione: Report --}}
    <div class="sidebar-section">{{ __('Report') }}</div>
    <nav class="sidebar-nav">
        @if (auth()->user()?->canUseLicenseFeature(\App\Models\License::FEATURE_STATISTICS))
            <a class="sidebar-link {{ request()->routeIs('admin.statistics.*') ? 'active' : '' }}"
                href="{{ route('admin.statistics.index') }}" title="{{ __('Statistiche') }}">
                <i class="fa-solid fa-chart-column"></i><span>{{ __('Statistiche') }}</span>
            </a>
        @endif
        <a class="sidebar-link {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}"
            href="{{ route('admin.documents.index') }}" title="{{ __('Stato documenti') }}">
            <i class="fa-solid fa-folder-open"></i><span>{{ __('Stato documenti') }}</span>
        </a>
    </nav>

    {{-- Sezione: Sistema --}}
    <div class="sidebar-section">{{ __('Sistema') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}"
            href="{{ route('admin.groups.index') }}" title="{{ __('Gruppi') }}">
            <i class="fa-solid fa-users"></i><span>{{ __('Gruppi') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
            href="{{ route('admin.notifications.edit') }}" title="{{ __('Notifiche') }}">
            <i class="fa-solid fa-bell"></i><span>{{ __('Notifiche') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.activity-log.*') ? 'active' : '' }}"
            href="{{ route('admin.activity-log.index') }}" title="{{ __('Registro Attività') }}">
            <i class="fa-solid fa-clock-rotate-left"></i><span>{{ __('Registro Attività') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
            href="{{ route('admin.settings.index') }}" title="{{ __('Impostazioni') }}">
            <i class="fa-solid fa-gear"></i><span>{{ __('Impostazioni') }}</span>
        </a>
        @if (auth()->user()?->isSuperadmin())
            <a class="sidebar-link" href="{{ route('superadmin.dashboard') }}" title="{{ __('Pannello Superadmin') }}">
                <i class="fa-solid fa-crown"></i><span>{{ __('Pannello Superadmin') }}</span>
            </a>
        @endif
    </nav>

    {{-- Footer sidebar: utente + logout --}}
    <div class="sidebar-footer">
        @auth
            <div class="sidebar-user">
                <a href="{{ route('profile.edit') }}" class="sidebar-user-link" title="{{ __('Il tuo account') }}">
                    <span class="sidebar-user-avatar"><i class="fa-solid fa-circle-user"></i></span>
                    <span class="sidebar-user-name">{{ Auth::user()->name }}</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none"
                    data-single-submit="true">
                    @csrf
                </form>
                <a href="{{ route('logout') }}" class="sidebar-logout" title="{{ __('Logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        @endauth
    </div>
</aside>

{{--
    Nav mobile (<768px): sidebar sostituita da top bar + bottom nav
    (restyle "Saru", ADR §19), con safe-area per notch/home indicator.
--}}
<nav class="bottom-nav" aria-label="{{ __('Navigazione principale') }}">
    <a class="bottom-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
        <i class="fa-solid fa-gauge-high"></i><span>{{ __('Dashboard') }}</span>
    </a>
    <a class="bottom-nav-link {{ request()->routeIs('admin.vehicles.*') ? 'active' : '' }}"
        href="{{ route('admin.vehicles.index') }}">
        <i class="fa-solid fa-truck"></i><span>{{ __('Veicoli') }}</span>
    </a>
    <div class="bottom-nav-add">
        <button type="button" class="bottom-nav-add-btn" id="bottom-nav-add-toggle" aria-haspopup="true"
            aria-expanded="false" aria-label="{{ __('Azione rapida') }}">
            <i class="fa-solid fa-plus"></i>
        </button>
        <div class="bottom-nav-add-menu" id="bottom-nav-add-menu">
            <a href="{{ route('admin.vehicles.create') }}"><i class="fa-solid fa-truck"></i>
                {{ __('Nuovo veicolo') }}</a>
            <a href="{{ route('admin.issues.create') }}"><i class="fa-solid fa-triangle-exclamation"></i>
                {{ __('Nuovo guasto') }}</a>
            <a href="{{ route('admin.fuel-logs.create') }}"><i class="fa-solid fa-gas-pump"></i>
                {{ __('Nuovo rifornimento') }}</a>
            <a href="{{ route('admin.maintenance-records.create') }}"><i class="fa-solid fa-calendar-check"></i>
                {{ __('Nuovo appuntamento') }}</a>
        </div>
    </div>
    <a class="bottom-nav-link {{ request()->routeIs('admin.issues.*') ? 'active' : '' }}"
        href="{{ route('admin.issues.index') }}">
        <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('Guasti') }}</span>
        @if ($sidebarOpenIssuesCount > 0)
            <span class="bottom-nav-badge">{{ $sidebarOpenIssuesCount }}</span>
        @endif
    </a>
    <button type="button" class="bottom-nav-link" id="bottom-nav-menu-toggle">
        <i class="fa-solid fa-bars"></i><span>{{ __('Menu') }}</span>
    </button>
</nav>
