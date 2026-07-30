<header>
    <nav class="navbar navbar-expand-lg bg-body-tertiary shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold" href="{{ route('dashboard') }}">
                <i class="fa-solid fa-car me-2"></i>
                <span class="brand-text">{{ config('app.name', 'Gods Backoffice') }}</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left Side Of Navbar -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    {{-- Dashboard --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}" title="{{ __('Dashboard') }}">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span class="nav-label ms-1">{{ __('Dashboard') }}</span>
                        </a>
                    </li>

                    {{-- Dropdown: Veicoli --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="{{ __('Veicoli') }}">
                            <i class="fa-solid fa-truck"></i>
                            <span class="nav-label ms-1">{{ __('Veicoli') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.vehicles.index') }}">
                                    <i class="fa-solid fa-car me-2"></i>{{ __('Veicoli') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.vehicle-types.index') }}">
                                    <i class="fa-solid fa-tags me-2"></i>{{ __('Tipi di veicoli') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.mileage-logs.index') }}">
                                    <i class="fa-solid fa-road me-2"></i>{{ __('Chilometraggi') }}
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Dropdown: Manutenzione --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="{{ __('Manutenzione') }}">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            <span class="nav-label ms-1">{{ __('Manutenzione') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.providers.index') }}">
                                    <i class="fa-solid fa-building me-2"></i>{{ __('Officine') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.issues.index') }}">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ __('Guasti') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.maintenance-records.index') }}">
                                    <i class="fa-solid fa-calendar-check me-2"></i>{{ __('Appuntamenti') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.deadlines.index') }}">
                                    <i class="fa-solid fa-clock me-2"></i>{{ __('Scadenze') }}
                                </a>
                            </li>
                        </ul>
                    </li>

                    {{-- Dropdown: Attrezzature --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="{{ __('Attrezzature') }}">
                            <i class="fa-solid fa-toolbox"></i>
                            <span class="nav-label ms-1">{{ __('Attrezzature') }}</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.equipments.index') }}">
                                    <i class="fa-solid fa-toolbox me-2"></i>{{ __('Attrezzature') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.equipment-types.index') }}">
                                    <i class="fa-solid fa-tag me-2"></i>{{ __('Tipi di Attrezzature') }}
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ms-auto align-items-center">
                    {{-- Theme Toggle --}}
                    <li class="nav-item me-2">
                        <button id="theme-toggle" class="btn btn-sm btn-outline-secondary rounded-pill" type="button"
                            aria-label="Cambia tema">
                            <i id="theme-toggle-icon" class="fa-solid fa-moon"></i>
                        </button>
                    </li>

                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}" title="{{ __('Login') }}">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span class="nav-label ms-1">{{ __('Login') }}</span>
                            </a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}" title="{{ __('Register') }}">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span class="nav-label ms-1">{{ __('Register') }}</span>
                                </a>
                            </li>
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle d-flex align-items-center"
                                href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
                                aria-expanded="false" title="{{ Auth::user()->name }}">
                                <i class="fa-solid fa-circle-user fs-5"></i>
                                <span class="nav-label fw-semibold ms-1">{{ Auth::user()->name }}</span>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="{{ url('profile') }}">
                                    <i class="fa-solid fa-user me-2"></i>{{ __('Profile') }}
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                    onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i>{{ __('Logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none"
                                    data-single-submit="true">
                                    @csrf
                                </form>
                            </div>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>
</header>
