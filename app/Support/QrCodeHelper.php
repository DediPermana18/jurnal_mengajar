<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeHelper
{
    /**
     * Render data menjadi QR Code SVG mentah (siap di-embed inline di Blade).
     */
    public static function svg(string $data, int $scale = 5): string
    {
        $options = new QROptions([
            'outputInterface'  => QRMarkupSVG::class,
            'eccLevel'         => EccLevel::L,
            'scale'            => $scale,
            'outputBase64'     => false,
            'drawLightModules' => false,
            'connectPaths'     => true,
        ]);

        $svg = (new QRCode($options))->render($data);

        // Buang deklarasi XML agar aman di-embed inline di halaman HTML
        $svg = preg_replace('/<\?xml.*?\?>\s*/s', '', $svg) ?? $svg;

        return trim($svg);
    }
}