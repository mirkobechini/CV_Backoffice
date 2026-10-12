<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * league/flysystem-aws-s3-v3 non è una dipendenza diretta di laravel/framework,
 * solo "suggerita": senza richiederla esplicitamente in composer.json, il driver
 * 's3' (usato in produzione per R2) crasha con "Class ... not found" solo quando
 * qualcosa lo risolve davvero — gli altri test lo evitano con Storage::fake('public').
 */
class S3DiskResolvesTest extends TestCase
{
    public function test_s3_disk_driver_resolves_without_missing_class_error(): void
    {
        // Bucket fittizio: basta per istanziare l'adapter, non serve una
        // chiamata di rete reale né credenziali valide.
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);

        // Basta risolvere il disco (come fa VerifyBackup::handle()): la
        // creazione dell'adapter S3 istanzia già le classi del driver, senza
        // bisogno di una chiamata di rete.
        Storage::disk('s3');

        $this->assertTrue(true);
    }
}
