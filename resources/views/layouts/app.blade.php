<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CV Backoffice') }}</title>

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
    @vite(['resources/js/app.js'])

    @livewireStyles
    @stack('styles')
</head>

<body>
    <div class="app-dp">
        @include('admin.partials.header')

        <main class="main-dp">
            {{-- Topbar: notifiche + theme toggle --}}
            <div class="topbar-dp">
                <div class="topbar-spacer"></div>
                <div class="topbar-actions">
                    <a href="{{ route('notifications.index') }}" class="topbar-btn position-relative"
                        title="{{ __('Notifiche') }}">
                        <i class="fa-solid fa-bell"></i>
                        @if (auth()->user()?->notifications()->where('is_read', false)->count() > 0)
                            <span
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ auth()->user()?->notifications()->where('is_read', false)->count() }}</span>
                        @endif
                    </a>
                    <button id="theme-toggle" class="topbar-btn" type="button" aria-label="{{ __('Cambia tema') }}">
                        <i id="theme-toggle-icon" class="fa-solid fa-moon"></i>
                    </button>
                </div>
            </div>

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
