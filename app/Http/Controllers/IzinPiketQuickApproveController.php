<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\PengaturanJadwal;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Persetujuan CEPAT (quick approve) tahap "Menunggu Piket" via tautan unik.
 *
 * Tautan `token_piket` disiarkan via WA ke Guru Piket bertugas pada tanggal
 * pengajuan + Waka Piket (broadcast di FonnteService::notifyGuruPiketIzinBaru).
 * Bahkan tanpa login, penerima dapat membuka link lalu menyetujui dengan satu
 * ketukan:
 *   - Petugas PERTAMA yang menyetujui  => status maju sesuai level approval
 *     (level 3 -> Menunggu Waka SDM, level 2 -> Menunggu Kepsek, level 1 -> final).
 *   - Petugas LAIN yang membuka link yang sama => melihat pemberitahuan
 *     "izin sudah diproses oleh piket lain" (anti double approval).
 *
 * Fallback fail-safe: bila tidak ada jadwal piket pada tanggal izin, Waka SDM
 * / Waka Kesiswaan ikut menjadi verifikator yang sah (alur tidak tersangkut).
 */
class IzinPiketQuickApproveController extends Controller
{
    protected function resolveIzin($token): ?IzinGuru
    {
        return IzinGuru::with(['user', 'approverPiket'])
            ->where('token_piket', $token)
            ->first();
    }

    /**
     * Daftar verifikator sah tahap "Menunggu Piket":
     *   - Guru Piket yang bertugas pada tanggal izin (jadwal_piket); plus
     *   - Waka Piket (sub-role 'waka_piket').
     *   - Fallback: bila TIDAK ada jadwal piket hari itu -> Waka Piket +
     *     Waka SDM / Waka Kesiswaan (penerima notifikasi pengganti).
     */
    protected function daftarVerifikator(IzinGuru $izin): Collection
    {
        $piketHari = FonnteService::guruPiketBertugasTanggal($izin->tanggal);
        $wakaPiket = User::wakaPiketUsers();

        if ($piketHari->isNotEmpty()) {
            return $piketHari->merge($wakaPiket)->unique('id')->values();
        }

        $wakaSdm = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->whereIn('sub_role', ['waka_sdm', 'waka_kesiswaan'])
            ->orderBy('nama')
            ->get();

        return $wakaPiket->merge($wakaSdm)->unique('id')->values();
    }

    /**
     * ID user aktif yang TIDAK boleh melakukan approval (self-approval).
     */
    protected function activeUserIds(): array
    {
        $ids = array_filter([
            auth()->id(),
            session('impersonate_target_id'),
            session('simulated_user_id'),
        ], fn ($v) => $v !== null && $v !== '' && (int) $v > 0);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    protected function isSelfOwnerIzin(IzinGuru $izin): bool
    {
        return in_array((int) $izin->user_id, $this->activeUserIds(), true);
    }

    /**
     * Resolve ID verifikator (Guru Piket / Waka Piket) yang menyetujui:
     * 1. Input dropdown `approved_by_piket_id` — wajib salah satu petugas sah;
     * 2. User login yang terdaftar sebagai petugas sah hari itu;
     * 3. Fallback otomatis bila hanya ada SATU verifikator sah.
     */
    protected function resolvePiketId(Request $request, IzinGuru $izin): ?int
    {
        $daftar = $this->daftarVerifikator($izin);
        if ($daftar->isEmpty()) {
            return null;
        }

        $kandidat = $daftar->pluck('id')->map(fn ($id) => (int) $id)->all();

        $submitted = $request->input('approved_by_piket_id');
        if ($submitted !== null && $submitted !== '' && in_array((int) $submitted, $kandidat, true)) {
            return (int) $submitted;
        }

        if (auth()->check()) {
            $authId = (int) auth()->id();
            if (in_array($authId, $kandidat, true)) {
                return $authId;
            }
        }

        if (count($kandidat) === 1) {
            return $kandidat[0];
        }

        return null;
    }

    /**
     * Halaman quick-approve Guru Piket (publik via link WA).
     *
     * State: invalid | ready | processed | approved | rejected.
     * - ready     : status masih pending_piket & belum ada approved_by_piket.
     * - processed : sudah diverifikasi piket lain / tahap sudah maju (anti double).
     */
    public function show($token)
    {
        $izin = $this->resolveIzin($token);

        if (! $izin) {
            return $this->render($token, 'invalid', null, collect());
        }

        if ($izin->isApproved()) {
            return $this->render($token, 'approved', $izin, $this->daftarVerifikator($izin));
        }

        if ($izin->isRejected()) {
            return $this->render($token, 'rejected', $izin, $this->daftarVerifikator($izin));
        }

        $daftarPiket = $this->daftarVerifikator($izin);

        // Sudah diproses piket lain / tahap sudah maju -> anti double approval.
        if ($izin->status !== IzinGuru::STATUS_PENDING_PIKET || $izin->approved_by_piket) {
            return $this->render($token, 'processed', $izin, $daftarPiket);
        }

        return $this->render($token, 'ready', $izin, $daftarPiket);
    }

    protected function render(string $token, string $state, ?IzinGuru $izin, Collection $daftarPiket)
    {
        $authPiket = null;
        if ($izin && auth()->check() && $daftarPiket->isNotEmpty()) {
            foreach ($daftarPiket as $piket) {
                if ((int) $piket->id === (int) auth()->id()) {
                    $authPiket = $piket;
                    break;
                }
            }
        }

        return view('piket.quick_approve', [
            'state' => $state,
            'izin' => $izin,
            'token' => $token,
            'daftarPiket' => $daftarPiket,
            'authPiket' => $authPiket,
            'isSelfOwner' => $izin ? $this->isSelfOwnerIzin($izin) : false,
            'level' => PengaturanJadwal::izinApprovalLevel(),
        ]);
    }

    /**
     * Proses quick-approve Guru Piket (POST dari halaman tautan).
     */
    public function approve(Request $request, $token)
    {
        $izin = $this->resolveIzin($token);

        if (! $izin) {
            return redirect()->route('piket.quick-approve.show', $token)
                ->with('error', 'Tautan quick-approve Guru Piket tidak valid atau telah kedaluwarsa.');
        }

        // Guard: izin data testing hanya dapat diverifikasi oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        // Anti double-approval: status sudah maju / piket lain sudah menyetujui.
        if ($izin->status !== IzinGuru::STATUS_PENDING_PIKET || $izin->approved_by_piket) {
            return redirect()->route('piket.quick-approve.show', $token)
                ->with('error', 'Pengajuan izin ini sudah diproses oleh Guru Piket lain (atau tahapnya sudah berjalan). Tautan tidak dapat digunakan lagi.');
        }

        // Guard: cegah self-approval — pemilik izin tidak boleh menyetujui sendiri.
        if ($this->isSelfOwnerIzin($izin)) {
            return redirect()->route('piket.quick-approve.show', $token)
                ->with('error', 'Anda tidak dapat menyetujui pengajuan izin Anda sendiri.');
        }

        $piketId = $this->resolvePiketId($request, $izin);
        if (! $piketId) {
            return back()->withInput()
                ->with('error', 'Silakan pilih Guru Piket / Waka Piket yang bertugas pada tanggal tersebut terlebih dahulu.');
        }

        $level = PengaturanJadwal::izinApprovalLevel();

        $data = [
            'approved_by_piket' => $piketId,
            'catatan_penolakan' => null,
        ];

        if ($level === 3) {
            // Spec: setelah diverifikasi piket -> 'Menunggu Waka SDM'.
            $data['status'] = IzinGuru::STATUS_PENDING_WAKA;
        } elseif ($level === 2) {
            $data['status'] = IzinGuru::STATUS_PENDING_KEPSEK;
        } else {
            $data['status'] = IzinGuru::STATUS_DISETUJUI;
            $data['approved_at'] = now();
        }

        $izin->update($data);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->route('piket.quick-approve.show', $token)
            ->with('success', 'Pengajuan izin berhasil diverifikasi. Status sekarang: '.$izin->status_label.'.');
    }
}