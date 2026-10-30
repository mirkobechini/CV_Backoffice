<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Gestione Flotta') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#e2792d">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Gestione Flotta') }}">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }
    </script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'auto';
            const theme = savedTheme === 'auto' ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>


    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Usando Vite -->
    {{-- app.js prima di app.css: app.js importa anche il CSS di Flatpickr, che
    deve essere sovrascritto dal nostro tema in app.css (l'ordine dei tag
    <link> nel documento segue l'ordine qui, quindi l'ultimo vince a parità
    di specificità). --}}
    @vite(['resources/js/app.js', 'resources/css/app.css'])

    @livewireStyles
    @stack('styles')
</head>

<body>
    <div class="app-dp {{ $__env->hasSection('form-topbar-title') ? 'is-form-page' : '' }}">
        @include('admin.partials.header')
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

        <main class="main-dp">
            {{-- Topbar: menu mobile + breadcrumb + azioni + theme switch.
            Sotto i 768px, le pagine di form (create/edit) dichiarano
            `form-topbar-title`/`form-topbar-cancel-url` per sostituire
            hamburger+logo+breadcrumb con "Annulla | Titolo" come nel
            mockup — stesso meccanismo riusabile per gli altri tipi di
            pagina (liste/dettagli) quando li rivedremo (restyle "Saru",
            ADR §19). --}}
            <div class="topbar-dp {{ $__env->hasSection('form-topbar-title') ? 'topbar-dp-form' : '' }}">
                <div class="topbar-start topbar-start-default">
                    <button type="button" class="topbar-btn menu-toggle" id="sidebar-toggle"
                        aria-label="{{ __('Apri il menu') }}">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <a href="{{ route('dashboard') }}" class="topbar-logo" aria-label="{{ __('Dashboard') }}">
                        <svg viewBox="0 0 377.83 376.2" width="22" height="22" fill="none">
                            <path fill="#e2792d" d="M154.51,139.94c18.01-.08,23.11,12.1,23.11,12.1,13.79-8.68,22.55,0,22.55,0,3.17-12.33,22.11-12.1,22.11-12.1h64.55c1.82.08,3.39.37,4.85.75-11.45-18.78-32.06-31.36-55.67-31.36-18.53,0-35.22,7.77-47.09,20.18-11.87-12.41-28.56-20.18-47.09-20.18-23.61,0-44.23,12.59-55.68,31.39,1.93-.58,3.33-.77,3.33-.77,0,0,47.02.08,65.03,0Z" />
                            <path stroke="#e2792d" stroke-width="11" stroke-miterlimit="10" fill="none" d="M312.35,139.31s23.06-15.98,39.63-3.74c25.59,18.89,37.13,83.96-27.59,105.81,0,0,11.78,55.83-81.97,114.16-54.83,34.11-107.02,0-107.02,0-96.63-53.43-81.97-114.16-81.97-114.16C-11.28,219.52.25,154.45,25.84,135.56c16.57-12.24,39.63,3.74,39.63,3.74,0,0,.44-74.8,64.69-108.77,0,0-4.6,10.33-2.23,24.98,0,0,38.13-54.94,104.1-49.67,0,0-15.24,12.05-16.56,23.08,0,0,32.22-11.46,49.07-2.51,0,0-19.24,8.92-18.23,19.76,0,0,59.81,22.38,66.03,93.12Z" />
                            <path fill="#e2792d" d="M155.58,150.62c-11.77,0-64.19,0-64.19,0,0,0-6.22-.36-6.69,5.74-.48,6.1,6.46,49.13,6.46,49.13,0,0,1.26,7.53,17.57,7.89h36.22s17.15-10.22,20.32-16.38c3.17-6.16,4.18-37.65,4.18-37.65,0,0-2.09-8.73-13.87-8.73Z" />
                            <path fill="#e2792d" d="M293.03,156.32c-.48-6.1-6.69-5.74-6.69-5.74,0,0-52.42,0-64.19,0-11.77,0-13.87,8.73-13.87,8.73,0,0,1.02,31.5,4.18,37.65,3.17,6.16,20.32,16.38,20.32,16.38h36.22c16.32-.36,17.57-7.89,17.57-7.89,0,0,6.93-43.03,6.46-49.13Z" />
                            <path fill="#e2792d" d="M279.95,222.66c-5.43,1.37-9.98,1.12-9.98,1.12l-40.28.12s-20.74-9.92-26.91-21.76c-6.17-11.83-5.25-43.38-5.25-43.38-9.26-7.28-17.57-.02-17.57-.02,0,0,.78,31.68-5.18,43.75-5.95,12.07-26.98,21.28-26.98,21.28,0,0-19.54,1.49-42.08,0-2.94-.19-5.51-.59-7.8-1.11,9.61,8.75,21.82,14.7,35.36,16.47-4.75,8.69-7.45,18.65-7.45,29.25,0,33.75,27.36,61.1,61.1,61.1s61.1-27.36,61.1-61.1c0-10.44-2.63-20.27-7.25-28.87,15.05-1.1,28.66-7.3,39.16-16.87Z" />
                        </svg>
                    </a>
                    @hasSection('breadcrumb')
                        @yield('breadcrumb')
                    @else
                        <div class="topbar-spacer"></div>
                    @endif
                </div>
                @hasSection('form-topbar-title')
                    <div class="topbar-form-bar">
                        <a href="{{ $__env->yieldContent('form-topbar-cancel-url') }}"
                            class="topbar-form-cancel">{{ __('Annulla') }}</a>
                        <span class="topbar-form-title">@yield('form-topbar-title')</span>
                        <span class="topbar-form-spacer" aria-hidden="true"></span>
                    </div>
                @endif
                <div class="topbar-actions">
                    <div class="theme-switch" id="theme-switch" role="group" aria-label="{{ __('Cambia tema') }}">
                        <button type="button" data-theme="auto" class="on">
                            <i class="fa-solid fa-circle-half-stroke theme-switch-icon" aria-hidden="true"></i>
                            <span class="theme-switch-label">{{ __('Auto') }}</span>
                        </button>
                        <button type="button" data-theme="light" title="{{ __('Chiaro') }}"
                            aria-label="{{ __('Chiaro') }}">☀️</button>
                        <button type="button" data-theme="dark" title="{{ __('Scuro') }}"
                            aria-label="{{ __('Scuro') }}">🌙</button>
                    </div>
                    <a href="{{ route('notifications.index') }}" class="topbar-btn position-relative"
                        title="{{ __('Notifiche') }}" aria-label="{{ __('Notifiche') }}">
                        <i class="fa-solid fa-bell"></i>
                        @if (auth()->user()?->notifications()->where('is_read', false)->count() > 0)
                            <span
                                class="position-absolute top-0 inset-s-100 translate-middle badge rounded-pill bg-danger">{{ auth()->user()?->notifications()->where('is_read', false)->count() }}</span>
                        @endif
                    </a>
                </div>
            </div>

            {{--
                Audit 2026-10-22 (B10): la stragrande maggioranza dei
                redirect dei controller admin (~80) passa una frase già
                pronta con with('status', '...'), ma questo layout non la
                renderizzava affatto — restava invisibile ovunque tranne
                nelle ~6 viste che la stampavano da sole. Alcuni flussi
                Breeze/profilo riusano invece 'status' come codice interno
                breve (es. 'profile-updated'), già tradotto in un messaggio
                dedicato dalla vista stessa: quelli vanno esclusi qui, o
                finirebbe in pagina il codice grezzo invece della frase.
            --}}
            @php($rawStatusCodes = [
                'profile-updated', 'token-created', 'token-revoked', 'password-updated',
                'verification-link-sent', 'telegram-link-generated', 'telegram-unlinked',
                'two-factor-confirmed', 'two-factor-disabled', 'recovery-codes-regenerated',
            ])
            @if (session('status') && ! in_array(session('status'), $rawStatusCodes, true))
                <div class="container pt-3">
                    <div class="alert success">{{ session('status') }}</div>
                </div>
            @endif

            @php($activeGroupLicense = auth()->user()?->activeGroup()?->currentLicense())
            @if ($activeGroupLicense?->isExpired())
                <div class="container pt-3">
                    <div class="alert alert-warning" role="alert">
                        {{ __('La licenza del tuo gruppo è scaduta il :date. Alcune funzionalità e la creazione di nuovi veicoli/utenti potrebbero essere bloccate finché non viene rinnovata.', ['date' => $activeGroupLicense->expires_at->format('d/m/Y')]) }}
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="container pt-3">
                    <div class="alert alert-danger" role="alert">
                        <strong>Controlla i dati inseriti:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @include('partials.cookie-banner')
    @livewireScripts
    @stack('scripts')
</body>

</html>
