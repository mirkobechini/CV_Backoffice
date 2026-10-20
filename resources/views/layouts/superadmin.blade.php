<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Superadmin — {{ config('app.name', 'CV Backoffice') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'auto';
            const theme = savedTheme === 'auto' ?
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') :
                savedTheme;
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @stack('styles')
</head>

<body>
    <div class="app-dp">
        @include('superadmin.partials.header')
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

        <main class="main-dp">
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
            </div>

            @if (session('status'))
                <div class="container pt-3">
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
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
    @stack('scripts')
</body>

</html>
