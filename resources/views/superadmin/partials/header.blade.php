<aside class="sidebar-dp">
    {{-- Brand: palette diversa dal pannello /admin (toni ambra invece di blu),
    per segnalare a colpo d'occhio che non si è nel pannello di un'associazione. --}}
    <a class="sidebar-brand" href="{{ route('superadmin.dashboard') }}">
        <span class="sidebar-logo"><i class="fa-solid fa-crown"></i></span>
        <span class="brand-text">Superadmin</span>
        <span class="badge text-bg-warning ms-auto small" style="font-size: 0.6rem;">{{ config('app.version') }}</span>
    </a>

    <div class="sidebar-section">{{ __('Piattaforma') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}"
            href="{{ route('superadmin.dashboard') }}" title="{{ __('Associazioni') }}">
            <i class="fa-solid fa-building-shield"></i><span>{{ __('Associazioni') }}</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('superadmin.discount-codes.*') ? 'active' : '' }}"
            href="{{ route('superadmin.discount-codes.index') }}" title="{{ __('Codici sconto') }}">
            <i class="fa-solid fa-ticket"></i><span>{{ __('Codici sconto') }}</span>
        </a>
    </nav>

    <div class="sidebar-section">{{ __('Il tuo pannello') }}</div>
    <nav class="sidebar-nav">
        <a class="sidebar-link" href="{{ route('dashboard') }}" title="{{ __('Torna al tuo pannello') }}">
            <i class="fa-solid fa-arrow-left"></i><span>{{ __('Torna ad /admin') }}</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        @auth
            <div class="sidebar-user">
                <a href="{{ route('profile.edit') }}" class="sidebar-user-link" title="{{ __('Il tuo account') }}">
                    <span class="sidebar-user-avatar"><i class="fa-solid fa-circle-user"></i></span>
                    <span class="sidebar-user-name">{{ Auth::user()->name }}</span>
                </a>
                <form id="logout-form-superadmin" action="{{ route('logout') }}" method="POST" class="d-none"
                    data-single-submit="true">
                    @csrf
                </form>
                <a href="{{ route('logout') }}" class="sidebar-logout" title="{{ __('Logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form-superadmin').submit();">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        @endauth
    </div>
</aside>
