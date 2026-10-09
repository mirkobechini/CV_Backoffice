<?php

namespace Tests\Unit;

use App\Support\UploadedFileUrl;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ->url() su un bucket S3/R2 pubblico produce un link permanente e
 * indovinabile (stesso problema di sicurezza del disco locale "public",
 * solo su un altro host). Su "s3" deve usare un link firmato con scadenza,
 * così il bucket può restare privato (audit sicurezza 2026-10-09).
 */
class UploadedFileUrlTest extends TestCase
{
    public function test_returns_null_for_null_path(): void
    {
        $this->assertNull(UploadedFileUrl::for(null));
    }

    public function test_uses_temporary_url_when_disk_is_s3(): void
    {
        config(['filesystems.uploads_disk' => 's3']);

        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->with('foo/bar.pdf', \Mockery::type(Carbon::class))
            ->andReturn('https://signed.example.com/foo/bar.pdf?signature=abc');

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $url = UploadedFileUrl::for('foo/bar.pdf');

        $this->assertSame('https://signed.example.com/foo/bar.pdf?signature=abc', $url);
    }

    public function test_uses_plain_url_when_disk_is_public(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('foo/bar.pdf', 'contenuto');

        $url = UploadedFileUrl::for('foo/bar.pdf');

        $this->assertSame(Storage::disk('public')->url('foo/bar.pdf'), $url);
    }
}
