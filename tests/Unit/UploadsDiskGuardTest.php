<?php

namespace Tests\Unit;

use App\Support\UploadsDiskGuard;
use Tests\TestCase;

/**
 * UPLOADS_DISK governa sia i backup del database sia i documenti caricati
 * (carta di circolazione, foto guasti): se omessa in produzione, l'app
 * ripiegava in silenzio sul disco pubblico "public" invece di fallire,
 * esponendo dump del database e documenti con dati personali senza alcuna
 * autenticazione (audit sicurezza 2026-10-09).
 */
class UploadsDiskGuardTest extends TestCase
{
    public function test_throws_in_production_with_public_disk(): void
    {
        $this->expectException(\RuntimeException::class);

        UploadsDiskGuard::assertSafeForEnvironment('production', 'public');
    }

    public function test_throws_in_production_when_disk_is_null(): void
    {
        $this->expectException(\RuntimeException::class);

        UploadsDiskGuard::assertSafeForEnvironment('production', null);
    }

    public function test_does_not_throw_in_production_with_s3_disk(): void
    {
        UploadsDiskGuard::assertSafeForEnvironment('production', 's3');

        $this->assertTrue(true);
    }

    public function test_does_not_throw_outside_production_with_public_disk(): void
    {
        UploadsDiskGuard::assertSafeForEnvironment('local', 'public');
        UploadsDiskGuard::assertSafeForEnvironment('testing', 'public');
        UploadsDiskGuard::assertSafeForEnvironment('staging', 'public');

        $this->assertTrue(true);
    }
}
