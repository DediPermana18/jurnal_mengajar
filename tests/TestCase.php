<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Body JSON sukses yang realistis untuk endpoint Fonnte /send.
     *
     * Bentuk ini disalin dari respons nyata api.fonnte.com, bukan dikarang,
     * karena perangkat lunak kini memverifikasi field `status` (lihat
     * FonnteService::send()).
     */
    protected static function responsFonnteSukses(array $override = []): array
    {
        return $override + [
            'detail' => 'success! message in queue',
            'id' => ['80367170'],
            'process' => 'pending',
            'requestid' => 2937124,
            'status' => true,
            'target' => ['6281234567890'],
        ];
    }

    /**
     * Fake respons API Fonnte dengan bentuk JSON yang sesuai perilaku nyata.
     *
     * WAJIB menyertakan body JSON: Fonnte membalas HTTP 200 BAHKAN saat gagal
     * secara bisnis (token invalid, perangkat terputus, target tidak valid,
     * kuota habis), sehingga body response adalah satu-satunya pembuktian
     * hasil kirim. `Http::fake()` telanjang membalas 200 tanpa body dan tidak
     * mewakili perilaku Fonnte sungguhan.
     *
     * @param  array|string|null  $body  Body respons (array = di-encode JSON,
     *                                   string = body mentah apa adanya).
     * @param  int  $status  Kode HTTP.
     */
    protected function fakeFonnte(array|string|null $body = null, int $status = 200): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(
                $body ?? self::responsFonnteSukses(),
                $status
            ),
        ]);
    }

    /**
     * Fake Fonnte dengan respons gagal "canonical" (HTTP 200 + status false),
     * yaitu bentuk yang dulu disalahartikan sebagai "berhasil terkirim".
     */
    protected function fakeFonnteGagal(string $reason = 'invalid token', int $status = 200): void
    {
        $this->fakeFonnte([
            'reason' => $reason,
            'requestid' => 2937124,
            'status' => false,
        ], $status);
    }
}
