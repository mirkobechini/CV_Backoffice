<?php

namespace App\Support;

/**
 * UPLOADS_DISK governa sia i backup del database sia i documenti caricati
 * dagli utenti (carta di circolazione, foto guasti/attrezzature): il disco
 * "public" è servito senza alcuna autenticazione da APP_URL/storage. In
 * produzione, se la env var viene semplicemente omessa in un deploy, l'app
 * ripiegava in silenzio su "public" invece di fallire, esponendo dump
 * completi del database e documenti con dati personali a chiunque
 * indovinasse/enumerasse un nome file (audit sicurezza 2026-10-09).
 */
class UploadsDiskGuard
{
    public static function assertSafeForEnvironment(string $environment, ?string $disk): void
    {
        if ($environment === 'production' && ($disk === 'public' || $disk === null)) {
            throw new \RuntimeException(
                'UPLOADS_DISK non può essere "public" (o non impostato) in produzione: '
                . 'backup del database e documenti caricati (carta di circolazione, foto guasti) '
                . 'sarebbero pubblicamente accessibili senza autenticazione. '
                . 'Imposta UPLOADS_DISK=s3 con le relative credenziali R2 (vedi docs/DEPLOY.md).'
            );
        }
    }
}
