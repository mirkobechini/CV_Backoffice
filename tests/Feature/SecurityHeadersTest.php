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
}
