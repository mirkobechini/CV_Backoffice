<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Gestione Flotta') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4f46e5">
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
    <div class="app-dp">
        @include('admin.partials.header')
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

        <main class="main-dp">
            {{-- Topbar: menu mobile + breadcrumb + azioni + theme switch --}}
            <div class="topbar-dp">
                <div class="topbar-start">
                    <button type="button" class="topbar-btn menu-toggle" id="sidebar-toggle"
                        aria-label="{{ __('Apri il menu') }}">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    @hasSection('breadcrumb')
                        @yield('breadcrumb')
                    @else
                        <div class="topbar-spacer"></div>
                    @endif
                </div>
                <div class="topbar-actions">
                    <div class="theme-switch" id="theme-switch" role="group" aria-label="{{ __('Cambia tema') }}">
                        <button type="button" data-theme="auto" class="on">{{ __('Auto') }}</button>
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
