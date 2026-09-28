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

        // Quick-view dashboard: hanya pengajuan yang MEMBUTUHKAN TTD/APPROVAL
        // Waka Kesiswaan (belum di-TTD + status aktif berjalan), dibatasi 5 data
        // agar dashboard ringkas dan tidak menyaingi halaman Approval yang
        // memegang manajemen tabel lengkap (search, filter tanggal, pagination).
        $riwayatMenunggu = $this->dispensasiBaseQuery()
            ->whereNull('ttd_waka')
            ->where('tipe_dispen', '!=', DispensasiSiswa::TIPE_MASUK)
            ->whereIn('status', self::PENDING_STATUSES)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('admin.waka-kesiswaan.dashboard', compact(
            'totalHariIni', 'pendingTtd', 'sudahTtd', 'totalSemua', 'riwayatMenunggu'
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

        $base = $this->dispensasiBaseQuery()
            ->where('tipe_dispen', '!=', DispensasiSiswa::TIPE_MASUK);

        // Pencarian teks: nama siswa, NIS, NISN, nomor surat (DIS-####/Tahun),
        // atau ID surat numerik.
        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->whereHas('siswa', function ($sq) use ($search) {
                    $sq->where('nama', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });

                if ($suratId = DispensasiSiswa::parseNomorSurat($search)) {
                    $q->orWhere('id', $suratId);
                } elseif (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        // Filter rentang tanggal (tanggal_mulai s.d. tanggal_selesai).
        $tanggalMulai = (string) $request->query('tanggal_mulai');
        $tanggalSelesai = (string) $request->query('tanggal_selesai');
        if ($tanggalMulai !== '' && $tanggalSelesai !== '') {
            $base->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai]);
        } elseif ($tanggalMulai !== '') {
            $base->whereDate('tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalSelesai !== '') {
            $base->whereDate('tanggal', '<=', $tanggalSelesai);
        }

        // Counter untuk badge di setiap tab (ikut menghormati search & rentang tanggal).
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

        $daftar = $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.waka-kesiswaan.approval', compact('daftar', 'filter', 'counts'));
    }

    /**
     * Tanda tangan digital massal (bulk): satu tanda tangan Waka Kesiswaan
     * diterapkan ke beberapa surat yang dipilih sekaligus. Hanya surat yang
     * masih isMenungguTtdWaka() yang diproses; sisanya dilewati.
     */
    public function approvalBulkStore(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'ttd_waka' => 'required|string|max:150000',
        ], [
            'ids.required' => 'Tidak ada surat yang dipilih untuk ditandatangani.',
            'ids.min' => 'Pilih minimal satu surat untuk ditandatangani.',
            'ttd_waka.required' => 'Tanda tangan Waka Kesiswaan wajib diisi.',
        ]);

        // Terima PNG (canvas default) maupun JPEG (canvas terkompresi dari frontend).
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

        $user = Auth::user();
        $isWakaAsli = $user && $user->isWakaKesiswaan();
        $isImpersonasi = $user && $user->hasActiveRole() && $user->activeRole() === 'waka_kesiswaan';

        abort_unless(
            $isWakaAsli || $isImpersonasi,
            403,
            'Akses ditolak. Halaman ini khusus untuk Waka Kesiswaan.'
        );

        $wakaId = Auth::id();
        $terproses = 0;

        $daftar = DispensasiSiswa::withoutGlobalScope(TestingDataScope::class)
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($daftar as $dispensasi) {
            // Data testing hanya boleh dimutasi Petugas IT / QA (impersonasi).
            if ($dispensasi->is_testing_data) {
                $this->authorizeTestingMutation($dispensasi);
            }

            if (! $dispensasi->isMenungguTtdWaka()) {
                continue;
            }

            $dispensasi->update([
                'ttd_waka' => $ttdWaka,
                'waka_kesiswaan_id' => $wakaId,
                'status' => DispensasiSiswa::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $wakaId,
            ]);
            $terproses++;
        }

        if ($terproses === 0) {
            $errMsg = 'Tidak ada surat terpilih yang masih menunggu tanda tangan Waka Kesiswaan.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }

            return back()->withErrors(['ids' => $errMsg]);
        }

        $successMsg = "{$terproses} surat dispensasi berhasil ditandatangani Waka Kesiswaan secara massal.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $successMsg]);
        }

        return back()->with('success', $successMsg);
    }

    /**
     * Builder dasar dispensasi portal Waka Kesiswaan.
     *
     * Saat Petugas IT impersonasi 'waka_kesiswaan', TestingDataScope memfilter
     * ke is_testing_data = true sehingga dispensasi real tidak muncul. Bypass
     * scope (+ relasi tanpa scope) agar daftar real + testing lengkap tampil —
     * perilaku yang sama dengan yang sudah diterapkan di approvalIndex.
     */
    protected function dispensasiBaseQuery()
    {
        $user = Auth::user();
        $isImpersonasi = $user && $user->hasActiveRole() && $user->activeRole() === 'waka_kesiswaan';

        return $isImpersonasi
            ? DispensasiSiswa::withoutGlobalScope(TestingDataScope::class)->with($this->crossScopeRelations())
            : DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'wakaKesiswaan', 'approver']);
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
