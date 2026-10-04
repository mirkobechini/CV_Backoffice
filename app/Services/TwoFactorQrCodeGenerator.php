<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * SVG del QR code da scansionare con l'app authenticator durante
 * l'attivazione del 2FA. pragmarx/google2fa genera solo l'URI
 * otpauth://, il rendering a immagine serve bacon/bacon-qr-code.
 */
class TwoFactorQrCodeGenerator
{
    public function svg(User $user): string
    {
        $uri = (new Google2FA())->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->two_factor_secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($uri);
    }
}
