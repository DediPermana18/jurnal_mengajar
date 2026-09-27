<?php

namespace App\Http\Controllers;

use App\Models\SecurityDevice;
use App\Models\SecurityLog;
use App\Services\SecurityAuditService;
use App\Services\SingleDeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Halaman "Perangkat & Keamanan" — jejak digital login (audit trail).
 *
 *  - index() : daftar seluruh perangkat yang pernah/sedang login (IP, browser,
 *              waktu) + penanda sesi aktif & sesi yang sedang dipakai, plus
 *              sidik jari perangkat & penanda perangkat baru/tak dikenal.
 *  - name()  : "Beri Nama Perangkat Ini" — nama kustom tersimpan di tabel
 *              `security_devices` (metadata tampilan, BUKAN audit).
 *  - revoke(): memutuskan sesi perangkat asing — sesi HTTP-nya di-invalidasi
 *              di tabel `sessions`, lalu user diarahkan langsung ke form
 *              Ganti Password (tab password halaman profil).
 *
 * KEBIJAKAN IMMUTABLE: tabel security_logs TIDAK pernah di-update maupun
 * di-hapus dari controller manapun — controller ini hanya membaca baris log.
 * Nama kustom perangkat tidak menyentuh baris security_logs.
 */
class SecurityDevicesController extends Controller
{
    public function __construct(private SecurityAuditService $auditService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $logs = $this->auditService->devicesFor(
            $user,
            (string) $request->session()->get(SingleDeviceSessionService::LOCK_KEY, ''),
            (string) $request->session()->getId(),
        );

        // Nama kustom perangkat (security_devices) — kunci per fingerprint.
        $customNames = SecurityDevice::query()
            ->where('user_id', $user->id)
            ->whereNotNull('name')
            ->pluck('name', 'fingerprint');

        foreach ($logs as $log) {
            $fingerprint = (string) $log->device_fingerprint;
            $meta = is_array($log->device_meta) ? $log->device_meta : [];

            $customName = isset($customNames[$fingerprint]) ? (string) $customNames[$fingerprint] : null;

            $log->fingerprint_short = $fingerprint !== '' ? substr($fingerprint, 0, 8) : '';
            $log->custom_name = $customName;
            $log->has_custom_name = $customName !== null && $customName !== '';
            $log->display_name = $log->has_custom_name ? $customName : $log->device_name;
            $log->is_generic = $this->isGenericDeviceName($log->device_name, $meta);
            $log->ip_short = $this->shortIp($log->ip_address);
            $log->login_time_short = optional($log->login_at)->format('H:i') ?? '';
        }

        return view('security.devices', ['logs' => $logs]);
    }

    /**
     * "Beri Nama Perangkat Ini" — simpan nama kustom per (user_id, fingerprint).
     *
     * Nama berlaku untuk SELURUH baris log dengan fingerprint yang sama dan
     * hanya boleh diatur pemilik fingerprint itu (baris security_logs miliknya).
     */
    public function name(Request $request)
    {
        $request->validate([
            'fingerprint' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        $fingerprint = trim((string) $request->input('fingerprint'));

        if ($fingerprint === '') {
            abort(404, 'Fingerprint perangkat tidak ditemukan.');
        }

        // Hanya pemilik fingerprint (tercatat di log miliknya) yang boleh menamainya.
        $owned = SecurityLog::query()
            ->where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->exists();

        if (! $owned) {
            abort(404, 'Fingerprint perangkat tidak ditemukan.');
        }

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            SecurityDevice::query()
                ->where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->delete();

            return back()->with('success_naming', 'Nama perangkat telah dihapus.');
        }

        SecurityDevice::updateOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $fingerprint],
            ['name' => $name],
        );

        return back()->with('success_naming', 'Nama perangkat berhasil disimpan.');
    }

    public function revoke(Request $request, SecurityLog $log)
    {
        $user = $request->user();

        // Pemilik akun hanya boleh memutuskan baris log miliknya sendiri.
        if ((int) $log->user_id !== (int) $user->id) {
            abort(404, 'Log perangkat tidak ditemukan.');
        }

        $result = $this->auditService->revokeDevice(
            $user,
            $log,
            (string) $request->session()->get(SingleDeviceSessionService::LOCK_KEY, ''),
            (string) $request->session()->getId(),
        );

        if (! $result['ok']) {
            return back()->withErrors(['device' => $result['message']]);
        }

        // Sesi asing sudah diputus → arahkan langsung ke form Ganti Password
        // (flash tab_aktif=password membuat halaman profil membuka tab tsb).
        return redirect()->route('profil.index')
            ->with('tab_aktif', 'password')
            ->with('success_password', $result['message']);
    }

    /**
     * Nama generik = perangkat tanpa model terdeteksi (mis. "Chrome • Android").
     *
     * Nama baru dari DeviceSignatureService selalu memuat model dalam tanda
     * kurung bila diketahui — ketiadaan kurung menandakan generik. Data
     * device_meta.model (kolom baru) diprioritaskan untuk baris modern.
     */
    private function isGenericDeviceName(?string $deviceName, array $meta): bool
    {
        $model = isset($meta['model']) ? trim((string) $meta['model']) : '';

        if ($model !== '') {
            return false;
        }

        if ($deviceName !== null && str_contains($deviceName, '(')) {
            return false;
        }

        return true;
    }

    /**
     * IP disamarkan untuk penanda visual (mis. "36.84.***.11");
     * IPv6 cukup 12 karakter pertama.
     */
    private function shortIp(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '-';
        }

        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0].'.'.$parts[1].'.***.'.$parts[3];
        }

        return Str::limit($ip, 12, '…');
    }
}