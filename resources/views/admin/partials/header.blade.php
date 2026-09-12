<aside class="sidebar-dp">
    {{-- Brand --}}
    <a class="sidebar-brand" href="{{ route('dashboard') }}">
        <span class="sidebar-logo"><i class="fa-solid fa-car"></i></span>
        <span class="brand-text">{{ config('app.name', 'CV Backoffice') }}</span>
        <span class="badge text-bg-secondary ms-auto small" style="font-size: 0.6rem;">{{ config('app.version') }}</span>
    </a>

    {{-- Sezione: Flotta --}}
    <div class="sidebar-section">{{ __('Flotta') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"
            title="{{ __('Dashboard') }}">
            <i class="fa-solid fa-gauge-high"></i><span>{{ __('Dashboard') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.vehicles.*') ? 'active' : '' }}"
            href="{{ route('admin.vehicles.index') }}" title="{{ __('Veicoli') }}">
            <i class="fa-solid fa-truck"></i><span>{{ __('Veicoli') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.vehicle-types.*') ? 'active' : '' }}"
            href="{{ route('admin.vehicle-types.index') }}" title="{{ __('Tipi di veicoli') }}">
            <i class="fa-solid fa-tags"></i><span>{{ __('Tipi') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.mileage-logs.*') ? 'active' : '' }}"
            href="{{ route('admin.mileage-logs.index') }}" title="{{ __('Chilometraggi') }}">
            <i class="fa-solid fa-road"></i><span>{{ __('Km') }}</span>
        </a>
    </nav>

    {{-- Sezione: Servizi --}}
    <div class="sidebar-section">{{ __('Servizi') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('admin.providers.*') ? 'active' : '' }}"
            href="{{ route('admin.providers.index') }}" title="{{ __('Officine') }}">
            <i class="fa-solid fa-building"></i><span>{{ __('Officine') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.issues.*') ? 'active' : '' }}"
            href="{{ route('admin.issues.index') }}" title="{{ __('Guasti') }}">
            <i class="fa-solid fa-triangle-exclamation"></i><span>{{ __('Guasti') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.maintenance-records.*') ? 'active' : '' }}"
            href="{{ route('admin.maintenance-records.index') }}" title="{{ __('Appuntamenti') }}">
            <i class="fa-solid fa-calendar-check"></i><span>{{ __('Appuntamenti') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.deadlines.*') ? 'active' : '' }}"
            href="{{ route('admin.deadlines.index') }}" title="{{ __('Scadenze') }}">
            <i class="fa-solid fa-clock"></i><span>{{ __('Scadenze') }}</span>
        </a>
    </nav>

    {{-- Sezione: Attrezzature --}}
    <div class="sidebar-section">{{ __('Attrezzature') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('admin.equipments.*') ? 'active' : '' }}"
            href="{{ route('admin.equipments.index') }}" title="{{ __('Attrezzature') }}">
            <i class="fa-solid fa-toolbox"></i><span>{{ __('Attrezzature') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('admin.equipment-types.*') ? 'active' : '' }}"
            href="{{ route('admin.equipment-types.index') }}" title="{{ __('Tipi di Attrezzature') }}">
            <i class="fa-solid fa-tag"></i><span>{{ __('Tipi Attrezzature') }}</span>
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
