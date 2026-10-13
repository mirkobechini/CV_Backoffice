<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * resend/resend-php non è una dipendenza diretta di laravel/framework, solo
 * "suggerita" (stesso problema di league/flysystem-aws-s3-v3 per il disco s3):
 * senza richiederla esplicitamente, risolvere il mailer 'resend' crasha con
 * "Class ... not found" solo quando qualcosa lo usa davvero — gli altri test
 * usano MAIL_MAILER=array e non lo avrebbero mai preso.
 */
class ResendMailerResolvesTest extends TestCase
{
    public function test_resend_mailer_resolves_without_missing_class_error(): void
    {
        config(['services.resend.key' => 'test-key']);

        Mail::mailer('resend');

        $this->assertTrue(true);
    }
}
