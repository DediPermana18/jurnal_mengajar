<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\IzinGuru;
use App\Models\PengaturanJadwal;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KurikulumIzinController extends Controller
{
    /**
     * Proteksi server-side: Waka Kurikulum & Admin
     */
    protected function authorizeKurikulum(): void
    {
        $role = auth()->user()?->role;
        $isAllowed = in_array($role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu'], true);

        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Approval Izin Guru.');
    }

    /**
     * Daftar pengajuan izin guru + filter status + ringkasan statistik.
     */
    public function index(Request $request)
    {
        $this->authorizeKurikulum();

        $filter = $request->input('status', 'Semua');

        $query = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])->latest();

        if (!in_array($filter, ['Semua'], true) && in_array($filter, IzinGuru::STATUSES, true)) {
            $query->where('status', $filter);
        }

        $counts = [];
        foreach (IzinGuru::STATUSES as $s) {
            $counts[$s] = IzinGuru::where('status', $s)->count();
        }

        $totalDisetujui = $counts[IzinGuru::STATUS_DISETUJUI];
        $totalDitolak   = $counts[IzinGuru::STATUS_DITOLAK];

        $level  = PengaturanJadwal::izinApprovalLevel();
        $daftarIzin = $query->paginate(15)->withQueryString();

        $noWaWaka   = PengaturanJadwal::noWaWakaIzin();
        $noWaKepsek = PengaturanJadwal::noWaKepsek();

        return view('kurikulum.izin.index', compact(
            'daftarIzin',
            'totalDisetujui',
            'totalDitolak',
            'filter',
            'counts',
            'level',
            'noWaWaka',
            'noWaKepsek'
        ));
    }

    /**
     * Menyetujui pengajuan izin guru (maju satu step sesuai level).
     * Hanya dapat diproses oleh Waka saat status = Pending Waka (giliran Waka).
     */
    public function approve($id)
    {
        $this->authorizeKurikulum();

        $izin = IzinGuru::with('user')->findOrFail($id);

        abort_unless($izin->status === IzinGuru::STATUS_PENDING_WAKA, 422, 'Hanya izin berstatus Pending Waka yang dapat disetujui pada langkah ini.');

        $level = PengaturanJadwal::izinApprovalLevel();
        $data  = ['catatan_penolakan' => null];

        $data['approved_by_waka'] = $izin->approved_by_waka ?? auth()->id();
        $data['status']           = match ($level) {
            2      => IzinGuru::STATUS_DISETUJUI,
            default => IzinGuru::STATUS_PENDING_KEPSEK,
        };

        if ($data['status'] === IzinGuru::STATUS_DISETUJUI) {
            $data['approved_at'] = now();
        }

        $izin->update($data);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->route('kurikulum.izin.index')
            ->with('success', "Izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} diproses ke status '{$izin->fresh()->status_label}'.");
    }

    /**
     * Menolak pengajuan izin guru beserta catatan penolakan.
     * Hanya dapat ditolak oleh Waka saat status = Pending Waka.
     */
    public function reject(Request $request, $id)
    {
        $this->authorizeKurikulum();

        $izin = IzinGuru::with('user')->findOrFail($id);

        abort_unless($izin->status === IzinGuru::STATUS_PENDING_WAKA, 422, 'Hanya izin berstatus Pending Waka yang dapat ditolak pada langkah ini.');

        $validated = $request->validate([
            'catatan_penolakan' => 'required|string|min:3|max:1000',
        ], [
            'catatan_penolakan.required' => 'Catatan penolakan wajib diisi.',
            'catatan_penolakan.min'      => 'Catatan penolakan minimal :min karakter.',
            'catatan_penolakan.max'      => 'Catatan penolakan maksimal :max karakter.',
        ]);

        $izin->update([
            'status'            => IzinGuru::STATUS_DITOLAK,
            'approved_at'       => now(),
            'catatan_penolakan' => $validated['catatan_penolakan'],
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->route('kurikulum.izin.index')
            ->with('success', "Izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} ditolak. Catatan penolakan telah disimpan.");
    }

    /**
     * Menampilkan lampiran / bukti surat izin guru.
     */
    public function showLampiran($id)
    {
        $this->authorizeKurikulum();

        $izin = IzinGuru::findOrFail($id);

        if (!$izin->lampiran) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (Storage::disk('public')->exists($izin->lampiran)) {
            return Storage::disk('public')->response($izin->lampiran);
        }

        if (Storage::disk('local')->exists($izin->lampiran)) {
            return Storage::disk('local')->response($izin->lampiran);
        }

        abort(404, 'Lampiran tidak ditemukan.');
    }
}
