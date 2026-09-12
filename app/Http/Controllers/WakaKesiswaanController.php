<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Portal Waka Kesiswaan: dashboard ringkas + peninjauan & penandatanganan
 * digital pengajuan dispensasi siswa saat login (langsung di aplikasi,
 * alternatif dari link approval publik /dispen/approve/{token}).
 */
class WakaKesiswaanController extends Controller
{
    protected const PENDING_STATUSES = [
        DispensasiSiswa::STATUS_PENDING,
        DispensasiSiswa::STATUS_PENDING_WAKA,
        DispensasiSiswa::STATUS_DISETUJUI,
    ];

    /**
     * Tab / filter status pada halaman approval (query param ?filter=).
     */
    protected const FILTERS = ['menunggu', 'disetujui', 'ditolak', 'semua'];

    /**
     * Dashboard Waka Kesiswaan.
     */
    public function dashboard()
    {
        $today = now()->toDateString();

        $totalHariIni = DispensasiSiswa::whereDate('tanggal', $today)->count();

        $pendingTtd = DispensasiSiswa::query()
            ->whereNull('ttd_waka')
            ->where('tipe_dispen', '!=', DispensasiSiswa::TIPE_MASUK)
            ->whereIn('status', self::PENDING_STATUSES)
            ->count();

        $sudahTtd = DispensasiSiswa::whereNotNull('ttd_waka')->count();

        $totalSemua = DispensasiSiswa::count();

        $riwayatTerbaru = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'wakaKesiswaan'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.waka-kesiswaan.dashboard', compact(
            'totalHariIni', 'pendingTtd', 'sudahTtd', 'totalSemua', 'riwayatTerbaru'
        ));
    }

    /**
     * Riwayat seluruh surat dispensasi dengan tab/filter status:
     * Menunggu TTD | Disetujui | Ditolak | Semua.
     *
     * Catatan: surat tipe "Masuk Kelas" tidak melalui alur TTD Waka Kesiswaan
     * (tidak memiliki kotak TTD Waka), sehingga tidak dimasukkan ke daftar.
     */
    public function approvalIndex(Request $request)
    {
        $filter = (string) $request->query('filter', 'menunggu');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'menunggu';
        }

        $base = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'wakaKesiswaan', 'approver'])
            ->where('tipe_dispen', '!=', DispensasiSiswa::TIPE_MASUK);

        // Counter untuk badge di setiap tab.
        $counts = [
            'menunggu' => (clone $base)->whereNull('ttd_waka')->whereIn('status', self::PENDING_STATUSES)->count(),
            'disetujui' => (clone $base)->whereNotNull('ttd_waka')->where('status', '!=', DispensasiSiswa::STATUS_DITOLAK)->count(),
            'ditolak' => (clone $base)->where('status', DispensasiSiswa::STATUS_DITOLAK)->count(),
            'semua' => (clone $base)->count(),
        ];

        $query = clone $base;

        switch ($filter) {
            case 'disetujui':
                $query->whereNotNull('ttd_waka')->where('status', '!=', DispensasiSiswa::STATUS_DITOLAK);
                break;
            case 'ditolak':
                $query->where('status', DispensasiSiswa::STATUS_DITOLAK);
                break;
            case 'semua':
                break;
            default: // menunggu
                $query->whereNull('ttd_waka')->whereIn('status', self::PENDING_STATUSES);
        }

        $daftar = $query->orderByDesc('tanggal')->orderByDesc('id')->get();

        return view('admin.waka-kesiswaan.approval', compact('daftar', 'filter', 'counts'));
    }

    /**
     * Waka Kesiswaan menandatangani (TTD) dispensasi saat login.
     */
    public function approvalStore(Request $request, $id)
    {
        $dispensasi = DispensasiSiswa::findOrFail($id);

        // Guard: surat data testing hanya dapat disetujui oleh IT/QA.
        $this->authorizeTestingMutation($dispensasi);

        abort_unless(
            $dispensasi->status !== DispensasiSiswa::STATUS_DITOLAK && empty($dispensasi->ttd_waka),
            422,
            'Dispensasi ini sudah ditandatangani atau tidak dapat disetujui lagi.'
        );

        $validated = $request->validate([
            'ttd_waka' => 'required|string|max:150000',
        ], [
            'ttd_waka.required' => 'Tanda tangan Waka Kesiswaan wajib diisi.',
            'ttd_waka.max' => 'Ukuran tanda tangan terlalu besar.',
        ]);

        $ttdWaka = preg_match('/^data:image\/png;base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (! $ttdWaka) {
            return back()->withErrors(['ttd_waka' => 'Tanda tangan Waka Kesiswaan wajib digambar terlebih dahulu.']);
        }

        $wakaId = Auth::id();

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'waka_kesiswaan_id' => $wakaId,
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $wakaId,
        ]);

        return back()->with('success', 'Surat dispensasi berhasil ditandatangani Waka Kesiswaan.');
    }
}
