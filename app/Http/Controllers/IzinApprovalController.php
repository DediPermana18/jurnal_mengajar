<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\PengaturanJadwal;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class IzinApprovalController extends Controller
{
    /**
     * Step approval publik ditentukan dari token yang dibuka DAN status
     * terbaru pengajuan di DB. Setiap token terikat ke satu tahap:
     *   - token_waka   -> hanya form Waka Kurikulum.
     *   - token_kepsek -> hanya form Kepala Sekolah (token baru hanya dirilis
     *                     setelah tahap Waka selesai).
     * Return [izin, state] — state: invalid | soon | waka | kepsek |
     * waka_done | approved | rejected.
     */
    protected function resolveStep($token)
    {
        $izin = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->where('token_waka', $token)
            ->orWhere('token_kepsek', $token)
            ->first();

        if (! $izin) {
            return [null, 'invalid'];
        }

        $role = $izin->token_kepsek === $token ? 'kepsek' : 'waka';
        $level = PengaturanJadwal::izinApprovalLevel();

        if ($izin->isApproved()) {
            return [$izin, 'approved'];
        }

        if ($izin->isRejected()) {
            return [$izin, 'rejected'];
        }

        switch ($role) {
            case 'waka':
                // Token Waka: HANYA berlaku saat giliran Waka di mode 3 level.
                if ($level === 3 && $izin->status === IzinGuru::STATUS_PENDING_WAKA) {
                    return [$izin, 'waka'];
                }

                // Waka sudah menandatangani -> tampilkan halaman "Sukses Verifikasi
                // Waka" (tanpa form apa pun, termasuk form Kepala Sekolah).
                if ($izin->status === IzinGuru::STATUS_PENDING_KEPSEK) {
                    return [$izin, 'waka_done'];
                }

                return [$izin, 'soon'];

            case 'kepsek':
                // Token Kepsek: HANYA berlaku saat giliran Kepala Sekolah.
                if ($izin->status === IzinGuru::STATUS_PENDING_KEPSEK) {
                    // Mode 3 level: pastikan tahap Waka benar-benar telah selesai
                    // sebelum form Kepala Sekolah aktif.
                    if ($level !== 3 || $this->isWakaStepDone($izin)) {
                        return [$izin, 'kepsek'];
                    }

                    return [$izin, 'soon'];
                }

                return [$izin, 'soon'];
        }

        return [$izin, 'soon'];
    }

    /**
     * Bukti bahwa langkah Waka Kurikulum telah diselesaikan.
     */
    protected function isWakaStepDone(IzinGuru $izin): bool
    {
        return (bool) ($izin->ttd_waka || $izin->approved_by_waka);
    }

    /**
     * Link WhatsApp untuk meneruskan token Kepsek setelah verifikasi Waka.
     */
    protected function kepsekWaUrl(IzinGuru $izin): ?string
    {
        $link = $izin->kepsek_approval_url;
        if (! $link) {
            return null;
        }

        $guru = $izin->user?->nama ?? 'Guru';
        $tanggal = $izin->tanggal?->translatedFormat('d F Y') ?? '-';

        $pesan = "Halo Bapak/Ibu Kepala Sekolah,\n"
            ."berikut pengajuan Izin Guru atas nama {$guru} pada {$tanggal}.\n\n"
            .'Pengajuan telah diverifikasi oleh Waka Kurikulum. Mohon menyetujui dan '
            ."menandatangani melalui link berikut:\n"
            .$link."\n\nTerima kasih.";

        $noWa = PengaturanJadwal::noWaKepsek();

        return 'https://wa.me/'.$noWa.'?text='.rawurlencode($pesan);
    }

    /**
     * Halaman persetujuan publik via link unik.
     * State: invalid | soon | waka | kepsek | waka_done | approved | rejected.
     */
    public function show($token)
    {
        [$izin, $state] = $this->resolveStep($token);

        if (! $izin) {
            return view('izin.approve', [
                'state' => 'invalid',
                'izin' => null,
                'step' => null,
                'token' => $token,
                'kepsek_link' => null,
                'kepsek_wa_url' => null,
            ]);
        }

        $step = in_array($state, ['waka', 'kepsek'], true) ? $state : null;

        return view('izin.approve', [
            'state' => $state,
            'izin' => $izin,
            'step' => $step,
            'token' => $token,
            'kepsek_link' => $izin->kepsek_approval_url,
            'kepsek_wa_url' => $this->kepsekWaUrl($izin),
        ]);
    }

    public function submit(Request $request, $token)
    {
        [$izin, $state] = $this->resolveStep($token);

        if (! $izin) {
            return redirect()->route('izin.approval.show', $token)
                ->with('error', 'Pengajuan izin ini sudah diproses atau tidak ditemukan.');
        }

        if (! in_array($state, ['waka', 'kepsek'], true)) {
            return redirect()->route('izin.approval.show', $token)
                ->with('error', 'Belum tiba saatnya langkah ini diproses.');
        }

        $step = $state;
        $keputusan = $request->input('keputusan');

        // === TOLAK (dari step publik) ===
        if ($keputusan === 'tolak') {
            $validated = $request->validate([
                'catatan_penolakan' => 'nullable|string|max:500',
            ]);

            $izin->update([
                'status' => IzinGuru::STATUS_DITOLAK,
                'approved_at' => now(),
                'catatan_penolakan' => trim($validated['catatan_penolakan'] ?? '') ?: 'Ditolak pada langkah '.ucfirst($step),
                'ttd_waka' => $step === 'waka' ? null : $izin->ttd_waka,
                'ttd_kepsek' => $step === 'kepsek' ? null : $izin->ttd_kepsek,
            ]);

            NotificationService::izinStatusChanged($izin->refresh());

            return redirect()->route('izin.approval.show', $token)
                ->with('success', "Izin '{$izin->user->nama}' pada {$izin->tanggal->translatedFormat('d F Y')} ditolak.");
        }

        // === SETUJUI ===
        $validated = $request->validate([
            'ttd_waka' => 'nullable|string|max:150000',
            'ttd_kepsek' => 'nullable|string|max:150000',
        ]);

        if ($step === 'waka') {
            $ttdWaka = $this->takeTtd($validated['ttd_waka'] ?? null);
            if (! $ttdWaka) {
                return redirect()->route('izin.approval.show', $token)
                    ->with('error', 'Tanda tangan Waka Kurikulum wajib diisi untuk menyetujui izin.');
            }

            $izin->update([
                'approved_by_waka' => $izin->approved_by_waka,
                'ttd_waka' => $ttdWaka,
                'status' => IzinGuru::STATUS_PENDING_KEPSEK,
            ]);

            NotificationService::izinStatusChanged($izin->refresh());

            return redirect()->route('izin.approval.show', $token)
                ->with('success', "Izin '{$izin->user->nama}' ditandatangani Waka Kurikulum. Silakan teruskan tautan Kepala Sekolah untuk persetujuan akhir.");
        }

        // Kepsek -> finalisasi
        $ttdKepsek = $this->takeTtd($validated['ttd_kepsek'] ?? null);
        if (! $ttdKepsek) {
            return redirect()->route('izin.approval.show', $token)
                ->with('error', 'Tanda tangan Kepala Sekolah wajib diisi untuk menyetujui izin.');
        }

        $izin->update([
            'approved_by_kepsek' => null,
            'ttd_kepsek' => $ttdKepsek,
            'status' => IzinGuru::STATUS_DISETUJUI,
            'approved_at' => now(),
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->route('izin.approval.show', $token)
            ->with('success', "Izin '{$izin->user->nama}' pada {$izin->tanggal->translatedFormat('d F Y')} berhasil DISETUJUI dan ditandatangani Kepala Sekolah.");
    }

    protected function takeTtd(?string $value): ?string
    {
        return isset($value) && preg_match('/^data:image\/png;base64,/i', trim($value))
            ? trim($value)
            : null;
    }
}
