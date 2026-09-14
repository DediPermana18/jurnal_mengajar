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
     *
     * SVG dari library hanya berisi viewBox (tanpa width/height), sehingga
     * beberapa browser menampilkan ukuran default 300x150 yang bisa tampak
     * "loading"/kosong. Untuk itu ukuran piksel dieksplisitkan di sini agar
     * QR langsung tampil pada ukuran yang diinginkan.
     */
    public static function svg(string $data, int $scale = 5, int $size = 200): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::L,
            'scale' => $scale,
            'outputBase64' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        $svg = (new QRCode($options))->render($data);

        // Buang deklarasi XML agar aman di-embed inline di halaman HTML
        $svg = preg_replace('/<\?xml.*?\?>\s*/s', '', $svg) ?? $svg;

        return self::withPixelSize(trim($svg), $size);
    }

    /**
     * Set width/height eksplisit pada elemen <svg> agar dirender dengan
     * ukuran pasti (bukan default browser), merata & langsung tampil.
     */
    private static function withPixelSize(string $svg, int $size): string
    {
        $svg = preg_replace('/<svg\b/', '<svg width="'.(int) $size.'" height="'.(int) $size.'"', $svg, 1);

        return $svg;
    }
}
