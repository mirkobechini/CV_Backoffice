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

    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @stack('styles')
</head>

<body class="guest-body">
    <div class="guest-wrap">
        <a href="{{ route('welcome') }}" class="guest-brand">
            <span class="guest-logo"><i class="fa-solid fa-car"></i></span>
            {{ config('app.name', 'CV Backoffice') }}
        </a>

        <div class="guest-card">
            @yield('content')
        </div>
    </div>

    @include('partials.cookie-banner')
    @stack('scripts')
</body>

</html>
