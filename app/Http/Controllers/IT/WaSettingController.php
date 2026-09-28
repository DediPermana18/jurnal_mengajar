<?php

namespace App\Http\Controllers\IT;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\FonnteService;
use App\Support\WaSendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan WhatsApp Gateway (Fonnte) — khusus Petugas IT / QA Tester.
 *
 * - GET  /it/settings/wa        -> halaman pengaturan + status koneksi.
 * - POST /it/settings/wa/update -> simpan token Fonnte (DB, fallback .env).
 * - POST /it/settings/wa/test   -> tes kirim WA ke nomor tertentu.
 * - POST /it/settings/wa/toggle -> nyalakan/matikan notifikasi WA (global).
 */
class WaSettingController extends Controller
{
    /**
     * Cache status koneksi gateway agar halaman tidak memukul API berulang.
     */
    protected const STATUS_CACHE_KEY = 'fonnte.connection_status';

    protected function authorizeIt(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof \App\Models\User && $user->isPetugasIt(),
            403,
            'Akses ditolak. Khusus Petugas IT / QA Tester.'
        );
    }

    /**
     * Halaman pengaturan: status koneksi + form token + form tes kirim.
     */
    public function index(Request $request)
    {
        $this->authorizeIt($request);

        $dbToken = (string) (AppSetting::get('fonnte_token') ?? '');
        $envToken = (string) (config('services.fonnte.token') ?? '');
        $activeToken = FonnteService::token();

        $status = Cache::remember(self::STATUS_CACHE_KEY, 60, fn () => FonnteService::checkConnection());

        $waEnabled = FonnteService::notificationsEnabled();

        return view('it.settings_wa', compact('dbToken', 'envToken', 'activeToken', 'status', 'waEnabled'));
    }

    /**
     * Simpan / perbarui token Fonnte di tabel app_settings.
     *
     * Kosongkan input lalu simpan => token DB dihapus, sistem kembali memakai
     * token dari .env (config/services.php).
     */
    public function update(Request $request)
    {
        $this->authorizeIt($request);

        $validated = $request->validate([
            'fonnte_token' => 'nullable|string|max:500',
        ]);

        $token = trim((string) ($validated['fonnte_token'] ?? ''));

        AppSetting::set('fonnte_token', $token);
        Cache::forget(self::STATUS_CACHE_KEY);

        if ($token === '') {
            return back()->with('success', 'Token Fonnte dihapus dari database. Sistem kini memakai token dari .env (config/services.php).');
        }

        return back()->with('success', 'Token Fonnte berhasil disimpan di database dan langsung aktif untuk seluruh notifikasi WA.');
    }

    /**
     * Nyalakan / matikan notifikasi WA (switch global).
     *
     * Status tersimpan di tabel `app_settings` (key `wa_notification_enabled`)
     * dan langsung memengaruhi seluruh pengiriman via FonnteService::sendNotification().
     */
    public function toggle(Request $request)
    {
        $this->authorizeIt($request);

        $enabled = $request->boolean('wa_notification_enabled');

        FonnteService::setNotificationsEnabled($enabled);

        return back()->with(
            'success',
            $enabled
                ? 'Notifikasi WA DIAKTIFKAN. Pesan WhatsApp akan kembali terkirim ke seluruh penerima.'
                : 'Notifikasi WA dimatikan. Pengiriman WhatsApp dihentikan secara global sampai diaktifkan kembali.'
        );
    }

    /**
     * Tes kirim pesan WA ke nomor tertentu untuk memverifikasi integrasi.
     */
    public function test(Request $request)
    {
        $this->authorizeIt($request);

        if (! FonnteService::notificationsEnabled()) {
            return back()->withInput()
                ->with('error', 'Pengiriman WA sedang dinonaktifkan oleh admin. Aktifkan "Status Layanan Notifikasi" terlebih dahulu sebelum mengirim pesan tes.');
        }

        $validated = $request->validate([
            'no_hp' => ['required', 'string', 'max:20'],
            'pesan' => ['required', 'string', 'max:1500'],
        ]);

        $target = $this->normalizeTarget($validated['no_hp']);
        if ($target === '') {
            return back()->withInput()->with('error', 'Format nomor tujuan tidak valid (hanya angka, +, -, spasi).');
        }

        Cache::forget(self::STATUS_CACHE_KEY);

        // `send()` (bukan `sendNotification()`) agar alasan kegagalan asli dari
        // Fonnte ditampilkan ke operator. Fonnte membalas HTTP 200 walau gagal
        // bisnis, jadi tidak boleh disimpulkan sukses dari kode HTTP saja.
        $hasil = FonnteService::send($target, $validated['pesan']);

        if ($hasil->ok) {
            return back()->with('success', "Pesan tes berhasil dikirim ke {$hasil->target} (Fonnte: {$this->ringkasanRespons($hasil)}).");
        }

        return back()->withInput()->with('error', 'Pesan tes GAGAL dikirim — '.$hasil->pesan());
    }

    /**
     * Ringkasan respons Fonnte untuk ditampilkan pada alert sukses (jumlah
     * pesan, request id, dan status antrean) sebagai bukti kiriman diterima
     * gateway — bukan sekadar "HTTP 200".
     */
    protected function ringkasanRespons(WaSendResult $hasil): string
    {
        $detail = $hasil->response['detail'] ?? null;
        $requestid = $hasil->response['requestid'] ?? null;

        $bagian = array_filter([
            is_string($detail) ? $detail : null,
            $requestid ? 'requestid '.$requestid : null,
        ]);

        return $bagian === [] ? 'status HTTP '.$hasil->httpStatus : implode(', ', $bagian);
    }

    /**
     * Normalisasi nomor WA ke format internasional tanpa awalan 0 (628xxx).
     *
     * Delegasi ke {@see FonnteService::normalizeTarget()} (sumber kebenaran
     * tunggal) agar format yang diuji di halaman ini identik dengan format yang
     * dipakai saat pengiriman notifikasi.
     */
    protected function normalizeTarget(string $no): string
    {
        return FonnteService::normalizeTarget($no);
    }
}