<?php

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * SVG del QR code da scansionare con l'app authenticator durante
 * l'attivazione del 2FA. pragmarx/google2fa genera solo l'URI
 * otpauth://, il rendering a immagine serve QrCodeGenerator (bacon/bacon-qr-code).
 */
class TwoFactorQrCodeGenerator
{
    public function __construct(
        private readonly QrCodeGenerator $qrCodeGenerator,
    ) {
    }

    public function svg(User $user): string
    {
        $uri = (new Google2FA())->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret,
        );

        return $this->qrCodeGenerator->svg($uri);
    }
}
