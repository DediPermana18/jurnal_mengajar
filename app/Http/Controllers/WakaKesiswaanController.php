<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use App\Models\Scopes\TestingDataScope;
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
     *
     * Catatan impersonasi: Saat Petugas IT impersonasi 'waka_kesiswaan', TestingDataScope
     * akan memfilter ke is_testing_data = true sehingga dispensasi real tidak muncul.
     * Bypass scope agar daftar lengkap (real + testing) tetap tampil.
     */
    public function approvalIndex(Request $request)
    {
        $filter = (string) $request->query('filter', 'menunggu');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'menunggu';
        }

        $user = Auth::user();
        $isImpersonasi = $user && $user->hasActiveRole() && $user->activeRole() === 'waka_kesiswaan';

        // Saat impersonasi, bypass TestingDataScope agar record real juga tampil,
        // dan relasi (siswa/kelas/guru/approver) juga di-resolve tanpa scope —
        // jika tidak, record real tampil namun kolom siswa hanya "-".
        $baseQuery = $isImpersonasi
            ? DispensasiSiswa::withoutGlobalScope(TestingDataScope::class)
                ->with($this->crossScopeRelations())
            : DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'wakaKesiswaan', 'approver']);

        $base = $baseQuery->where('tipe_dispen', '!=', DispensasiSiswa::TIPE_MASUK);

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
     *
     * Catatan untuk Petugas IT / QA Tester yang sedang impersonasi Waka Kesiswaan:
     * TestingDataScope memfilter query berdasarkan is_testing_data, sehingga record
     * dispensasi real (is_testing_data = 0) tidak akan ditemukan oleh findOrFail()
     * biasa. Gunakan withoutGlobalScope agar ID dapat di-resolve tanpa filter scope,
     * lalu validasi otorisasi secara eksplisit setelahnya.
     */
    public function approvalStore(Request $request, $id)
    {
        // Cari dispensasi tanpa TestingDataScope agar Petugas IT yang
        // impersonasi Waka Kesiswaan dapat menemukan record real maupun testing.
        $dispensasi = DispensasiSiswa::withoutGlobalScope(TestingDataScope::class)
            ->findOrFail($id);

        $user = Auth::user();

        // Guard otorisasi:
        // - Data testing (is_testing_data = true): hanya Petugas IT/QA yang boleh.
        // - Data real (is_testing_data = false): Waka Kesiswaan asli ATAU
        //   Petugas IT yang sedang impersonasi role waka_kesiswaan.
        if ($dispensasi->is_testing_data) {
            // Record testing → hanya IT yang boleh mutasi
            $this->authorizeTestingMutation($dispensasi);
        } else {
            // Record real → tolak jika bukan Waka Kesiswaan asli maupun impersonator
            $isWakaAsli = $user && $user->isWakaKesiswaan();
            $isImpersonasi = $user && $user->hasActiveRole()
                          && $user->activeRole() === 'waka_kesiswaan';
            abort_unless(
                $isWakaAsli || $isImpersonasi,
                403,
                'Akses ditolak. Halaman ini khusus untuk Waka Kesiswaan.'
            );
        }

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

        // Terima PNG (canvas default) maupun JPEG (canvas terkompresi dari frontend)
        $ttdWaka = preg_match('/^data:image\/(png|jpeg|jpg);base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (! $ttdWaka) {
            $errMsg = 'Tanda tangan Waka Kesiswaan wajib digambar terlebih dahulu.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }

            return back()->withErrors(['ttd_waka' => $errMsg]);
        }

        $wakaId = Auth::id();

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'waka_kesiswaan_id' => $wakaId,
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $wakaId,
        ]);

        $successMsg = 'Surat dispensasi berhasil ditandatangani Waka Kesiswaan.';

        // AJAX request (fetch dari frontend) → return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $successMsg]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Eager-load relasi tanpa TestingDataScope saat bypass scope aktif
     * (impersonasi IT sebagai Waka Kesiswaan), agar record real yang tampil
     * di daftar tetap menampilkan siswa/kelas/guru-nya (bukan "-").
     */
    protected function crossScopeRelations(): array
    {
        $withoutScope = fn ($query) => $query->withoutGlobalScope(TestingDataScope::class);

        return [
            'siswa' => $withoutScope,
            'siswa.kelas' => $withoutScope,
            'guruPiket' => $withoutScope,
            'approver' => $withoutScope,
            'wakaKesiswaan' => $withoutScope,
        ];
    }
}
