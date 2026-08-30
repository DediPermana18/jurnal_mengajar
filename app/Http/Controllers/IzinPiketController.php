<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IzinPiketController extends Controller
{
    /**
     * Akses ditentukan oleh jadwal piket pada hari berjalan (Guru Piket / Admin / Petugas IT).
     */
    protected function authorizePiket(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $allowed = $user->isPiketHariIni()
            || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PETUGAS_IT], true);

        abort_unless($allowed, 403, 'Akses ditolak. Hanya Guru Piket yang dapat memverifikasi pengajuan izin guru.');
    }

    /**
     * Antrian pengajuan izin guru yang menunggu verifikasi Guru Piket (Step 1).
     */
    public function index(Request $request)
    {
        $this->authorizePiket();

        $filter = $request->get('filter', 'pending_piket');
        $level  = PengaturanJadwal::izinApprovalLevel();

        $daftarIzin = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->when($filter !== 'semua', fn ($q) => $q->where('status', $filter))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        $totalPendingPiket = IzinGuru::where('status', IzinGuru::STATUS_PENDING_PIKET)->count();
        $totalPendingWaka  = IzinGuru::where('status', IzinGuru::STATUS_PENDING_WAKA)->count();
        $totalPendingKepsek = IzinGuru::where('status', IzinGuru::STATUS_PENDING_KEPSEK)->count();
        $totalDisetujui    = IzinGuru::where('status', IzinGuru::STATUS_DISETUJUI)->count();
        $totalDitolak      = IzinGuru::where('status', IzinGuru::STATUS_DITOLAK)->count();

        $noWaWaka   = PengaturanJadwal::noWaWakaIzin();
        $noWaKepsek = PengaturanJadwal::noWaKepsek();

        return view('piket.izin.index', compact(
            'daftarIzin',
            'filter',
            'level',
            'totalPendingPiket',
            'totalPendingWaka',
            'totalPendingKepsek',
            'totalDisetujui',
            'totalDitolak',
            'noWaWaka',
            'noWaKepsek',
        ));
    }

    /**
     * Verifikasi / jalankan Step 1 oleh Guru Piket.
     * Status akhir ditentukan oleh level approval yang dikonfigurasi.
     */
    public function approve(Request $request, $id)
    {
        $this->authorizePiket();

        $izin = IzinGuru::with('user')->findOrFail($id);

        abort_unless($izin->status === IzinGuru::STATUS_PENDING_PIKET, 422, 'Hanya izin yang menunggu verifikasi Piket yang dapat diproses pada langkah ini.');

        $level = PengaturanJadwal::izinApprovalLevel();

        $data = [
            'approved_by_piket' => Auth::id(),
            'catatan_penolakan' => null,
        ];

        if ($level === 3) {
            $data['status'] = IzinGuru::STATUS_PENDING_WAKA;
        } elseif ($level === 2) {
            $data['status'] = IzinGuru::STATUS_PENDING_KEPSEK;
        } else {
            $data['status']   = IzinGuru::STATUS_DISETUJUI;
            $data['approved_at'] = now();
        }

        $izin->update($data);

        $pesan = "Verifikasi Piket untuk izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} berhasil.";

        if ($level === 1) {
            $pesan .= ' (Alur 1 level: izin langsung DISETUJUI).';
        } elseif ($level === 2) {
            $pesan .= ' Lanjut menunggu persetujuan Kepala Sekolah.';
        } else {
            $pesan .= ' Lanjut menunggu persetujuan Waka.';
        }

        return redirect()->route('piket.izin.index')->with('success', $pesan);
    }

    /**
     * Tolak pengajuan izin (dari step mana pun) beserta catatan.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizePiket();

        $izin = IzinGuru::with('user')->findOrFail($id);

        abort_unless($izin->isPending(), 422, 'Hanya izin berstatus Pending yang dapat ditolak.');

        $validated = $request->validate([
            'catatan_penolakan' => 'required|string|min:3|max:1000',
        ]);

        $izin->update([
            'status'            => IzinGuru::STATUS_DITOLAK,
            'approved_at'       => now(),
            'catatan_penolakan' => $validated['catatan_penolakan'],
        ]);

        return redirect()->route('piket.izin.index')
            ->with('success', "Izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} ditolak.");
    }
}
