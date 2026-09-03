<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Crea un manifest Vite fittizio se non esiste, così i test possono
     * renderizzare le view senza dover buildare gli asset (npm run build).
     */
    protected function setUp(): void
    {
        parent::setUp();

        $manifestPath = public_path('build/manifest.json');
        if (!file_exists($manifestPath)) {
            $buildDir = public_path('build');
            if (!is_dir($buildDir)) {
                mkdir($buildDir, 0755, true);
            }

            $manifest = [
                'resources/js/app.js' => [
                    'file' => 'assets/app.js',
                    'isEntry' => true,
                    'src' => 'resources/js/app.js',
                ],
            ];

            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
