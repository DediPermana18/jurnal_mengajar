<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\PengaturanJadwal;
use Illuminate\Http\Request;

class IzinApprovalController extends Controller
{
    /**
     * Token yang membutuhkan persetujuan publik (Waka / Kepsek).
     * Return [izin, idApprover] atau null jika sudah final.
     */
    protected function resolveStep($token)
    {
        $izin = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->where('approval_token', $token)
            ->first();

        if (!$izin || !$izin->isPending()) {
            return [$izin, null];
        }

        $level = PengaturanJadwal::izinApprovalLevel();

        if ($level === 3 && $izin->status === IzinGuru::STATUS_PENDING_WAKA) {
            return [$izin, 'waka'];
        }

        if ($izin->status === IzinGuru::STATUS_PENDING_KEPSEK) {
            return [$izin, 'kepsek'];
        }

        // Level 1/2 belum mencapai tahap publik -> tidak ada langkah publik.
        return [$izin, null];
    }

    /**
     * Halaman persetujuan publik via link unik.
     * State: invalid | soon | waka | kepsek | approved | rejected.
     */
    public function show($token)
    {
        [$izin, $step] = $this->resolveStep($token);

        if (!$izin) {
            return view('izin.approve', ['state' => 'invalid', 'izin' => null, 'step' => null, 'token' => $token]);
        }

        $state = match (true) {
            $izin->isApproved() => 'approved',
            $izin->isRejected() => 'rejected',
            $step === null      => 'soon',
            default             => $step,
        };

        return view('izin.approve', ['state' => $state, 'izin' => $izin, 'step' => $step, 'token' => $token]);
    }

    public function submit(Request $request, $token)
    {
        [$izin, $step] = $this->resolveStep($token);

        if (!$izin) {
            return redirect()->route('izin.approval.show', $token)->with('error', 'Pengajuan izin ini sudah diproses atau tidak ditemukan.');
        }

        if (!$step) {
            return redirect()->route('izin.approval.show', $token)->with('error', 'Belum tiba saatnya langkah ini diproses.');
        }

        $keputusan = $request->input('keputusan');

        // === TOLAK (dari step publik) ===
        if ($keputusan === 'tolak') {
            $validated = $request->validate([
                'catatan_penolakan' => 'nullable|string|max:500',
            ]);

            $izin->update([
                'status'            => IzinGuru::STATUS_DITOLAK,
                'approved_at'       => now(),
                'catatan_penolakan' => trim($validated['catatan_penolakan'] ?? '') ?: 'Ditolak pada langkah ' . ucfirst($step),
                'ttd_waka'          => $step === 'waka' ? null : $izin->ttd_waka,
                'ttd_kepsek'        => $step === 'kepsek' ? null : $izin->ttd_kepsek,
            ]);

            return redirect()->route('izin.approval.show', $token)
                ->with('success', "Izin '{$izin->user->nama}' pada {$izin->tanggal->translatedFormat('d F Y')} ditolak.");
        }

        // === SETUJUI ===
        $validated = $request->validate([
            'ttd_waka'   => 'nullable|string|max:150000',
            'ttd_kepsek' => 'nullable|string|max:150000',
        ]);

        if ($step === 'waka') {
            $ttdWaka = $this->takeTtd($validated['ttd_waka'] ?? null);
            $izin->update([
                'approved_by_waka' => null,
                'ttd_waka'         => $ttdWaka,
                'status'           => IzinGuru::STATUS_PENDING_KEPSEK,
            ]);

            return redirect()->route('izin.approval.show', $token)
                ->with('success', "Izin '{$izin->user->nama}' disetujui Waka. Lanjut menunggu persetujuan & tanda tangan Kepala Sekolah.");
        }

        // Kepsek -> finalisasi
        $ttdKepsek = $this->takeTtd($validated['ttd_kepsek'] ?? null);
        if (!$ttdKepsek) {
            return redirect()->route('izin.approval.show', $token)
                ->with('error', 'Tanda tangan Kepala Sekolah wajib diisi untuk menyetujui izin.');
        }

        $izin->update([
            'approved_by_kepsek' => null,
            'ttd_kepsek'         => $ttdKepsek,
            'status'             => IzinGuru::STATUS_DISETUJUI,
            'approved_at'        => now(),
        ]);

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
