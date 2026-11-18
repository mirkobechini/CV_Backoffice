<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit sicurezza SL5: nessun CSP, nessun X-Frame-Options — un sito
 * malevolo poteva incorniciare l'app in un iframe (clickjacking).
 */
class SecurityHeadersTest extends TestCase
{
    public function test_web_responses_include_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_csp_allows_the_vite_dev_server_only_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' http://*:5173", $csp);
        $this->assertStringContainsString("font-src 'self' https://fonts.gstatic.com http://*:5173", $csp);
        $this->assertStringContainsString('ws://*:5173', $csp);

        $this->app['env'] = 'production';
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('5173', $csp);
    }
}
