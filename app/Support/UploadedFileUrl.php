<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * ->url() su un disco S3/R2 con bucket pubblico produce un link permanente
 * e indovinabile (stesso problema di sicurezza del disco "public" locale,
 * solo spostato su un altro host): usa invece un link firmato con scadenza,
 * così il bucket può restare privato. In locale ("public", filesystem
 * locale) temporaryUrl() non è supportato: lì resta il link normale, che
 * in sviluppo non ha bisogno di firma.
 */
class UploadedFileUrl
{
    public static function for(?string $path, int $minutes = 10): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = config('filesystems.uploads_disk');
        $disk = Storage::disk($diskName);

        if ($diskName === 's3') {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes));
        }

        return $disk->url($path);
    }
}
