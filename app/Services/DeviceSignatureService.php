<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Parser perangkat presisi + sidik jari unik untuk jejak keamanan login.
 *
 * Sumber informasi (diprioritaskan dari kiri ke kanan):
 *  1. Client Hints (Sec-CH-UA-*): platform, versi platform, brand browser,
 *     dan MODEL perangkat. Model & versi platform termasuk HIGH-ENTROPY —
 *     aplikasi mengirim header `Accept-CH` (middleware ConfigureClientHints)
 *     agar Chromium mengirimkannya pada request berikutnya.
 *  2. User-Agent fallback: OS, browser, engine, versi, dan model Android
 *     (model terdeteksi dari token "(Android 13; SM-S918B Build/...)").
 *  3. Data frontend `device_meta` (screen, timezone, language, cpu, touch)
 *     yang dikirim saat submit login / penyelesaian approval — dipakai untuk
 *     membedakan dua perangkat yang "Chrome • Android" walau model sama.
 *
 * Sidik jari (fingerprint) dengan sengaja TIDAK memakai IP: alamat IP berubah
 * setiap ganti jaringan seluler — memasukkannya ke hash hanya akan membuat
 * perangkat sah tampak sebagai "perangkat baru" berulang kali. IP tetap
 * disimpan terpisah (kolom ip_address) dan dipakai sebagai penanda tambahan
 * di halaman Perangkat & Keamanan.
 */
class DeviceSignatureService
{
    /** Panjang hash hex sidik jari yang disimpan di DB. */
    public const FINGERPRINT_HEX_LENGTH = 16;

    /** Mapping awalan kode model → merek. */
    private const BRAND_PREFIXES = [
        'SM-' => 'Samsung',
        'Redmi' => 'Xiaomi',
        'POCO' => 'Poco',
        'M201' => 'Xiaomi',
        '2201' => 'Xiaomi',
        'Pixel' => 'Google',
        'HUAWEI' => 'Huawei',
        'OPPO' => 'Oppo',
        'CPH' => 'Oppo',
        'realme' => 'Realme',
        'RMX' => 'Realme',
        'Vivo' => 'Vivo',
        'V2' => 'Vivo',
        'Infinix' => 'Infinix',
        'Tecno' => 'Tecno',
        'Moto' => 'Motorola',
        'XT' => 'Motorola',
        'LG-' => 'LG',
        'Nokia' => 'Nokia',
        'ASUS' => 'Asus',
        'Lenovo' => 'Lenovo',
        'Sony' => 'Sony',
    ];

    /**
     * Bangun profil perangkat dari satu request (header + input frontend).
     *
     * @return array{device_name: string, device_fingerprint: ?string,
     *               device_meta: array, is_generic: bool}
     */
    public function fromRequest(Request $request, ?array $meta = null): array
    {
        $ua = (string) $request->userAgent();
        $uaInfo = $this->parseUserAgent($ua);
        $hints = $this->parseClientHints($request);
        $meta = $this->sanitizeMeta($meta);

        // ---- Resolusi akhir (hints lebih presisi, fallback UA) ----
        $model = trim((string) (($hints['model'] ?? '') !== '' ? $hints['model'] : $uaInfo['model']));
        $os = ($hints['os'] ?? '') !== '' ? $hints['os'] : $uaInfo['os'];
        $osVersion = $this->normalizeOsVersion(
            $os,
            (string) (($hints['osVersion'] ?? '') !== '' ? $hints['osVersion'] : $uaInfo['osVersion'])
        );
        $browser = (string) (($hints['browser'] ?? '') !== '' ? $hints['browser'] : $uaInfo['browser']);
        $browserVersion = (string) (($hints['browserVersion'] ?? '') !== '' ? $hints['browserVersion'] : $uaInfo['browserVersion']);
        $engine = (string) $uaInfo['engine'];

        $deviceName = $this->friendlyName($browser, $browserVersion, $os, $osVersion, $model);

        $isGeneric = $model === '';

        $fingerprint = $this->fingerprint([
            'os' => $os,
            'engine' => $engine,
            'browser' => $browser,
            'model' => $model,
            'screen' => $meta['screen'] ?? null,
            'timezone' => $meta['timezone'] ?? null,
            'language' => $meta['language'] ?? null,
            'cores' => $meta['cores'] ?? null,
            'touch' => $meta['touch'] ?? null,
        ]);

        return [
            'device_name' => $deviceName,
            'device_fingerprint' => $fingerprint,
            'device_meta' => [
                'model' => $model !== '' ? $model : null,
                'os' => $os,
                'os_version' => $osVersion !== '' ? $osVersion : null,
                'browser' => $browser,
                'browser_version' => $browserVersion !== '' ? $browserVersion : null,
                'engine' => $engine,
                'screen' => $meta['screen'] ?? null,
                'timezone' => $meta['timezone'] ?? null,
                'language' => $meta['language'] ?? null,
                'cores' => $meta['cores'] ?? null,
                'touch' => $meta['touch'] ?? null,
                'is_generic' => $isGeneric,
            ],
            'is_generic' => $isGeneric,
        ];
    }

    /**
     * Nama ramah-manusia: "Chrome 126 • Windows 10/11",
     * "Samsung Internet 27 • Android 14 (Samsung SM-S918B)".
     */
    public function friendlyName(string $browser, string $browserVersion, string $os, string $osVersion, string $model): string
    {
        $browserLabel = trim($browser) !== '' ? ucfirst(trim($browser)) : 'Browser Tidak Dikenal';
        if ($browserVersion !== '') {
            $browserLabel .= ' '.$browserVersion;
        }

        $osLabel = trim($os) !== '' ? trim($os) : 'OS Tidak Dikenal';
        if ($osVersion !== '') {
            $osLabel .= ' '.$osVersion;
        }

        $name = "{$browserLabel} • {$osLabel}";

        if ($model !== '') {
            $name .= ' ('.$this->brandModel($model).')';
        }

        return $name;
    }

    /**
     * Sidik jari stabil per perangkat (hash SHA-256 → FINGERPRINT_HEX_LENGTH
     * karakter hex). Komponen dengan nilai kosong dibuang; urutan dinormalisasi
     * agar hasil hash tidak bergantung pada urutan input.
     */
    public function fingerprint(array $components): ?string
    {
        $pairs = [];
        foreach ($components as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $value = is_array($value) ? implode('|', array_map('strval', $value)) : (string) $value;
            if (trim($value) === '') {
                continue;
            }
            $pairs[] = $key.'='.trim($value);
        }

        if ($pairs === []) {
            return null;
        }

        asort($pairs, SORT_STRING);

        return substr(hash('sha256', implode('&', $pairs)), 0, self::FINGERPRINT_HEX_LENGTH);
    }

    /**
     * Baca data frontend `device_meta` dari request (array langsung atau JSON
     * string — dikirim oleh halaman login & penyelesaian approval).
     */
    public static function metaFromRequest(Request $request): ?array
    {
        $raw = $request->input('device_meta');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return null;
            }

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    // ==================== Internals ====================

    private function parseClientHints(Request $request): array
    {
        $model = trim((string) ($request->header('Sec-CH-UA-Model') ?? ''));
        if ($model === '' || strtolower($model) === '?0') {
            $model = '';
        }

        $platform = $this->cleanQuoted((string) ($request->header('Sec-CH-UA-Platform') ?? ''));
        if ($platform === '' || strtolower($platform) === '?0' || strtolower($platform) === 'unknown') {
            $platform = '';
        }
        $os = $platform !== '' ? $this->normalizePlatform($platform) : '';

        $platformVersion = $this->cleanQuoted((string) ($request->header('Sec-CH-UA-Platform-Version') ?? ''));
        if ($platformVersion === '' || strtolower($platformVersion) === '?0') {
            $platformVersion = '';
        }

        // Brand browser dari Sec-CH-UA-Full-Version-List / Sec-CH-UA
        // (mis. "Not)A;Brand";v="99"; "Google Chrome";v="126.0.6478.126").
        $brandHeader = (string) ($request->header('Sec-CH-UA-Full-Version-List')
            ?: $request->header('Sec-CH-UA') ?: '');
        [$browser, $browserVersion] = $this->brandFromList($brandHeader);

        return [
            'model' => $model !== '' ? $model : null,
            'os' => $os !== '' ? $os : null,
            'osVersion' => $platformVersion !== '' ? $platformVersion : null,
            'browser' => $browser !== '' ? $browser : null,
            'browserVersion' => $browserVersion !== '' ? $browserVersion : null,
        ];
    }

    /**
     * @return array{engine: string, browser: string, browserVersion: string,
     *               os: string, osVersion: string, model: string}
     */
    private function parseUserAgent(string $ua): array
    {
        $normal = trim($ua);

        // ---------- Engine ----------
        $engine = 'Lainnya';
        if (str_contains($normal, 'AppleWebKit')) {
            $blink = str_contains($normal, 'Chrome/')
                || str_contains($normal, 'Edg/')
                || str_contains($normal, 'OPR/')
                || str_contains($normal, 'SamsungBrowser/')
                || str_contains($normal, 'CriOS/');
            $engine = $blink ? 'Blink' : 'WebKit';
        } elseif (preg_match('/Gecko\//', $normal)) {
            $engine = 'Gecko';
        }

        // ---------- Browser ----------
        $browser = 'Browser Tidak Dikenal';
        $browserVersion = '';
        $browserRules = [
            'Edg/' => ['Edge', 'Edg/([\d.]+)'],
            'OPR/' => ['Opera', 'OPR/([\d.]+)'],
            'SamsungBrowser/' => ['Samsung Internet', 'SamsungBrowser/([\d.]+)'],
            'CriOS/' => ['Chrome (iOS)', 'CriOS/([\d.]+)'],
            'FxiOS/' => ['Firefox (iOS)', 'FxiOS/([\d.]+)'],
            'Firefox/' => ['Firefox', 'Firefox/([\d.]+)'],
            'Chrome/' => ['Chrome', 'Chrome/([\d.]+)'],
            'Version/' => ['Safari', 'Version/([\d.]+)'],
            'Safari/' => ['Safari', null],
        ];
        foreach ($browserRules as $needle => [$label, $versionPattern]) {
            if (stripos($normal, $needle) !== false) {
                $browser = $label;
                if ($versionPattern !== null && preg_match('~'.$versionPattern.'~', $normal, $m)) {
                    $browserVersion = $m[1];
                }
                break;
            }
        }

        // ---------- OS ----------
        $os = 'OS Tidak Dikenal';
        $osVersion = '';
        if (preg_match('/Windows NT ([\d.]+)/', $normal, $m)) {
            $os = 'Windows';
            $osVersion = match (true) {
                str_starts_with($m[1], '10.') => '10/11',
                str_starts_with($m[1], '6.3') => '8.1',
                str_starts_with($m[1], '6.2') => '8',
                str_starts_with($m[1], '6.1') => '7',
                default => $m[1],
            };
        } elseif (str_contains($normal, 'iPhone')) {
            $os = 'iOS';
            $osVersion = $this->iosVersionFromUa($normal);
        } elseif (str_contains($normal, 'iPad')) {
            $os = 'iPadOS';
            $osVersion = $this->iosVersionFromUa($normal);
        } elseif (preg_match('/Android ([\d.]+)/', $normal, $m)) {
            $os = 'Android';
            $osVersion = $m[1];
        } elseif (preg_match('/Mac OS X ([\d_]+)/', $normal, $m)) {
            $os = 'macOS';
            $osVersion = str_replace('_', '.', $m[1]);
        } elseif (str_contains($normal, 'CrOS')) {
            $os = 'ChromeOS';
        } elseif (str_contains($normal, 'Linux')) {
            $os = 'Linux';
        }

        // ---------- Model (Android: "Android 13; SM-S918B Build/...") ----------
        $model = '';
        if (preg_match('/Android [\d.]+; ([^;)]+)/i', $normal, $m)) {
            $candidate = trim((string) preg_replace('/\s*(?:wv|Build\/.*)$/i', '', $m[1]));
            $candidateLower = strtolower($candidate);
            if ($candidate !== '' && ! in_array($candidateLower, ['mobile', 'unknown', 'generic', 'tablet'], true)) {
                $model = $candidate;
            }
        }

        return [
            'engine' => $engine,
            'browser' => $browser,
            'browserVersion' => $browserVersion,
            'os' => $os,
            'osVersion' => $osVersion,
            'model' => $model,
        ];
    }

    private function iosVersionFromUa(string $ua): string
    {
        return preg_match('/OS ([\d_]+) like Mac OS X/', $ua, $m)
            ? str_replace('_', '.', $m[1])
            : '';
    }

    private function normalizePlatform(string $platform): string
    {
        return match (strtolower(trim($platform))) {
            'android' => 'Android',
            'windows' => 'Windows',
            'ios' => 'iOS',
            'macos', 'mac os' => 'macOS',
            'chrome os', 'chromeos' => 'ChromeOS',
            'linux' => 'Linux',
            default => trim($platform) !== '' ? ucfirst(trim($platform)) : 'OS Tidak Dikenal',
        };
    }

    private function normalizeOsVersion(string $os, string $version): string
    {
        if ($version === '') {
            return '';
        }

        $version = preg_replace('/\.0+$/', '', $version) ?? '';

        return match (true) {
            $os === 'Windows' => match (true) {
                str_starts_with($version, '10') || str_starts_with($version, '11') || str_starts_with($version, '15') => '',
                default => $version,
            },
            $os === 'Android' => implode('.', array_slice(explode('.', $version), 0, 2)),
            $os === 'iOS' || $os === 'iPadOS' => implode('.', array_slice(explode('.', $version), 0, 2)),
            default => $version,
        };
    }

    /**
     * Ekstrak brand browser + versi dari header brand list Client Hints.
     *
     * @return array{0: string, 1: string} [browser, version]
     */
    private function brandFromList(string $header): array
    {
        $brands = [];
        if (preg_match_all('/"([^"]+)"\s*;\s*v\s*=\s*"([^"]+)"/', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $brands[strtolower($match[1])] = $match[1].'|'.$match[2];
            }
        }

        if ($brands === []) {
            return ['', ''];
        }

        // Hindari brand penuh getir ("Not)A;Brand", "Chromium", "Google Chrome"
        // di urutan tertentu) → pilih brand browser utama yang paling dikenal.
        foreach (['microsoft edge', 'google chrome', 'opera gx', 'opera', 'firefox', 'safari'] as $known) {
            if (isset($brands[$known])) {
                [$label, $version] = explode('|', $brands[$known], 2);

                $label = match (true) {
                    $known === 'microsoft edge' => 'Edge',
                    $known === 'google chrome' => 'Chrome',
                    $known === 'opera gx' => 'Opera GX',
                    $known === 'opera' => 'Opera',
                    $known === 'firefox' => 'Firefox',
                    $known === 'safari' => 'Safari',
                    default => $label,
                };

                return [$label, $version];
            }
        }

        // Fallback: brand pertama yang bukan brand-gimmick Chromium.
        unset($brands['not)a;brand'], $brands['chromium']);

        $first = reset($brands);
        if ($first !== false) {
            [$label, $version] = explode('|', $first, 2);

            return [ucfirst($label), $version];
        }

        return ['', ''];
    }

    private function cleanQuoted(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\"'");
    }

    /**
     * Bersihkan & normalkan komponen frontend ke bentuk yang aman disimpan.
     */
    private function sanitizeMeta(?array $meta): array
    {
        if (! is_array($meta)) {
            return [];
        }

        $stringOrNull = function (string $key, int $max) use ($meta): ?string {
            if (! isset($meta[$key]) || ! is_scalar($meta[$key])) {
                return null;
            }

            $value = trim((string) $meta[$key]);

            return $value === '' ? null : mb_substr($value, 0, $max);
        };

        $intOrNull = function (string $key) use ($meta): ?int {
            if (! isset($meta[$key]) || ! is_numeric($meta[$key])) {
                return null;
            }

            $value = (int) $meta[$key];

            return $value >= 0 ? $value : null;
        };

        return [
            'screen' => $this->sanitizeScreen($meta['screen'] ?? null),
            'timezone' => $stringOrNull('timezone', 64),
            'language' => $stringOrNull('language', 32),
            'cores' => $intOrNull('cores'),
            'touch' => $intOrNull('touch'),
        ];
    }

    /**
     * Resolusi layar ternormalisasi "800x1280" (dimensi terbesar di depan)
     * plus color depth — rotasi layar tidak mengubah nilai hash.
     */
    private function sanitizeScreen(mixed $raw): ?string
    {
        if (! is_array($raw) || count($raw) < 2) {
            return null;
        }

        $width = (int) ($raw[0] ?? 0);
        $height = (int) ($raw[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        [$large, $small] = $width >= $height ? [$width, $height] : [$height, $width];
        $colorDepth = (int) ($raw[2] ?? 0);

        return "{$large}x{$small}".($colorDepth > 0 ? "@{$colorDepth}" : '');
    }

    /**
     * Tambahkan merek yang dikenal di depan kode model (SM-S918B → Samsung SM-S918B).
     */
    private function brandModel(string $model): string
    {
        $brand = null;
        foreach (self::BRAND_PREFIXES as $prefix => $candidateBrand) {
            if (str_starts_with($model, $prefix)) {
                $brand = $candidateBrand;
                break;
            }
        }

        $clean = trim((string) preg_replace('/\s+/', ' ', $model));

        return $brand !== null ? "{$brand} {$clean}" : $clean;
    }
}