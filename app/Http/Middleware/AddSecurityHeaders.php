<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nessun CSP, nessun X-Frame-Options (audit sicurezza SL5): senza, un sito
 * malevolo poteva incorniciare l'app in un iframe (clickjacking) e uno XSS
 * stored poteva caricare uno script/iframe da un host esterno qualsiasi.
 *
 * script-src/style-src includono 'unsafe-inline': l'app usa ampiamente
 * `<script>`/`style="..."` inline nelle viste Blade (toggle tema, form
 * dinamici, modali di completamento) — rimuoverli richiederebbe un
 * refactor a parte (nonce/hash per ogni inline), fuori scope qui. Il CSP
 * resta comunque utile: blocca comunque script/iframe/oggetti da host
 * esterni non in whitelist, il vettore più comune per uno XSS stored.
 */
class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $imgSrc = "'self' data:";
        if ($r2Host = parse_url((string) config('filesystems.disks.s3.url'), PHP_URL_HOST)) {
            $imgSrc .= " https://{$r2Host}";
        }

        // In locale il dev server Vite (HMR) serve script/CSS/font/websocket
        // sulla porta 5173 (vedi public/hot), con host variabile (localhost/
        // [::1]) — senza questi il CSP lo bloccava, rompendo lo sviluppo locale.
        // Chrome rifiuta un IPv6 letterale come host-source CSP, quindi si
        // usa il wildcard "*:5173". Non si applica in produzione (asset
        // compilati, stesso host).
        $viteDev = app()->environment('local') ? ' http://*:5173' : '';
        $viteDevWs = app()->environment('local') ? ' ws://*:5173' : '';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'{$viteDev}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$viteDev}",
            "font-src 'self' https://fonts.gstatic.com{$viteDev}",
            "img-src {$imgSrc}",
            "connect-src 'self'{$viteDev}{$viteDevWs}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
