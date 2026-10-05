<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code generico come SVG inline. Estratto da TwoFactorQrCodeGenerator
 * (che lo usa per l'URI otpauth://) perché serve anche per le etichette
 * QR di veicoli/attrezzature (vedi VehicleController::qrLabel()).
 */
class QrCodeGenerator
{
    public function svg(string $data, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($data);
    }
}
