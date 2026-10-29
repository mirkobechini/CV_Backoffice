<?php

use App\Http\Middleware\EnsureIsSuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'superadmin' => EnsureIsSuperAdmin::class,
        ]);

        // Autentica via cookie di sessione (oltre al token Bearer) le
        // richieste /api/* provenienti dal dominio dell'app stessa (vedi
        // config/sanctum.php 'stateful'): senza questo, fetch() dalla UI
        // admin verso /api/* (autocomplete guasti, ricerca veicoli in
        // sidebar) non si autenticava mai da browser — solo i test con
        // token Bearer coprivano queste route, nessun utente reale loggato.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
