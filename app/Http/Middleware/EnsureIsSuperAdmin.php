<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSuperAdmin
{
    /**
     * Protegge il namespace /superadmin: ortogonale ai ruoli di gruppo
     * (capo/sottocapo/member), verifica solo il flag di piattaforma.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSuperadmin()) {
            abort(403);
        }

        return $next($request);
    }
}
