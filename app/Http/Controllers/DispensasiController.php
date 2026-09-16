<?php

namespace App\Http\Controllers;

use App\Models\CatatanTerlambat;
use App\Models\DispensasiKolektif;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\PengaturanJadwal;
use App\Models\Scopes\TestingDataScope;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DispensasiController extends Controller
{
    /**
     * Akses ditentukan oleh jadwal_piket pada hari berjalan.
     */
    protected function authorizeGuruPiket(): void
    {
        $user = Auth::user();
        abort_unless(
            $user instanceof User
                && ($user->isPetugasIt() || $user->activeRole() === 'guru_piket' || $user->isPiketHariIni()),
            403,
            'Akses ditolak. Anda tidak mendapat jadwal piket hari ini.'
        );
    }

    /**
     * Akses untuk Waka Kurikulum / Admin Kurikulum (termasuk Petugas IT / QA).
     */
    protected function authorizeKurikulum(): void
    {
        $user = Auth::user();
        $allowedRoles = ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu'];

        abort_unless(
            $user instanceof User && ($user->isPetugasIt() || in_array($user->effectiveRole(), $allowedRoles, true)),
            403,
            'Akses ditolak. Anda tidak memiliki izin untuk approval dispensasi.'
        );
    }

    /**
     * Daftar dispensasi siswa (default: hari ini) + tombol buat dispen.
     */
    public function index(Request $request)
    {
        $this->authorizeGuruPiket();

        // Auto-Expired / Auto-Mangkir: surat aktif yang melewati batas langsung
        // tampil Kadaluarsa / Mangkir tanpa menunggu cron.
        DispensasiSiswa::refreshAutoExpired();
        DispensasiSiswa::refreshAutoMangkir();

        $today = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);

        $dataDispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->whereDate('tanggal', $tanggal)
            ->whereNull('dispensasi_kolektif_id')
            ->orderBy('id', 'desc')
            ->get();

        // Pengajuan kolektif (rombongan): header pengajuan.
        $dataKolektif = DispensasiKolektif::with(['guruPiket', 'siswaItems.siswa.kelas'])
            ->whereDate('tanggal', $tanggal)
            ->orderBy('id', 'desc')
            ->get();

        // Unifikasi 1 tabel utama: baris individu & kolektif digabung lalu
        // diurutkan berdasarkan created_at terbaru.
        $dataGabungan = $dataDispensasi
            ->map(fn (DispensasiSiswa $d) => ['tipe' => 'individu', 'dispen' => $d])
            ->concat($dataKolektif->map(fn (DispensasiKolektif $k) => ['tipe' => 'kolektif', 'kolektif' => $k]))
            ->sortByDesc(fn (array $row) => ($row['tipe'] === 'kolektif'
                ? $row['kolektif']->created_at
                : $row['dispen']->created_at)?->timestamp ?? 0)
            ->values();

        $totalHariIni = $dataGabungan->count();

        return view('piket.dispensasi.index', compact('dataGabungan', 'dataDispensasi', 'tanggal', 'today', 'totalHariIni'));
    }

    /**
     * Halaman form input dispensasi (siswa, jam ke-, alasan, & TTD canvas).
     * Menyertakan daftar jadwal KBM (hari/jam/mapel/guru) agar Guru Piket bisa
     * mengaitkan pengajuan ke mata pelajaran & Guru Mapel yang ditinggalkan.
     */
    public function create()
    {
        $this->authorizeGuruPiket();

        $dataSiswa = Siswa::with('kelas')
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        // Daftar kelas untuk dropdown "Pilih Kelas" (cascading ke dropdown siswa).
        $kelasList = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();

        // Saat validasi gagal (old()): pra-pilih kelas dari siswa yang terpilih
        // agar dropdown siswa & filter jadwal tetap konsisten setelah redirect back.
        $selectedKelas = null;
        $oldSiswaId = old('id_siswa');
        if ($oldSiswaId) {
            $selectedKelas = $dataSiswa->firstWhere('id', $oldSiswaId)?->id_kelas;
        }

        // Jika kelas terpilih (mis. dari error validasi), siswa yang dirender awal
        // cukup yang berasal dari kelas tersebut agar tidak perlu menunggu AJAX.
        $dataSiswa = $selectedKelas
            ? $dataSiswa->where('id_kelas', $selectedKelas)->values()
            : collect();

        // Data jam pelajaran lengkap (dengan jam_mulai, jam_selesai) untuk auto-select berdasarkan waktu sekarang
        $jamPelajaran = JamPelajaran::whereNotNull('jam_ke')
            ->select('jam_ke', 'jam_mulai', 'jam_selesai', 'jenis', 'kategori_hari')
            ->orderBy('jam_ke')
            ->get();

        // Master JP per kategori hari (Senin-Kamis vs Jumat) untuk dropdown dinamis
        // 'Dari JP'/'Sampai JP' yang mengikuti TANGGAL dispen terpilih, bukan hanya
        // kategori hari browser sekarang. Di-embed ke JS (MASTER_JP_PER_HARI) agar
        // render ulang mengikuti tanggal bisa dilakukan instan (sinkron).
        $jamPelajaranPerHari = JamPelajaran::whereNotNull('jam_ke')
            ->orderBy('jam_mulai')
            ->get()
            ->groupBy('kategori_hari')
            ->map(fn ($items) => $items->map(fn (JamPelajaran $jp) => [
                'id'      => (int) $jp->id,
                'jam_ke'  => (int) $jp->jam_ke,
                'mulai'   => substr((string) $jp->jam_mulai, 0, 5),
                'selesai' => substr((string) $jp->jam_selesai, 0, 5),
            ])->values()->all())
            ->all();

        // Master JP AKTIF HARI INI untuk dropdown rentang 'Dari JP' s/d 'Sampai JP' (Part 1).
        // Jumlah JP otomatis dinamis mengikuti Master Data Jam Pelajaran: kategori hari
        // (Senin-Kamis vs Jumat) menentukan berapa slot JP yang tersedia hari ini.
        $kategoriHari = now()->isFriday() ? 'Jumat' : 'Senin-Kamis';
        $jamPelajaranList = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->whereNotNull('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        // JP yang sedang aktif sekarang: default selected 'Dari JP' & 'Sampai JP'
        // (fallback server-side bila JS gagal mendeteksi waktu).
        $currentJp = $jamPelajaranList->firstWhere('jam_ke', $this->jamKeSaranSekarang());

        // Default dropdown "Boleh Masuk Mulai JP Ke-": JP yang sedang / segera berlangsung
        // berdasarkan waktu sistem sekarang (fallback server-side bila JS gagal).
        $jamMasukDefault = $this->jamKeSaranSekarang();

        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        // Baris siswa yang diisi sebelum validasi gagal (old()) agar daftar
        // siswa multi-baris di form kolektif tetap ter-restore setelah redirect back.
        $oldSiswaRows = collect();
        $oldSiswaIds = array_values(array_filter(array_map('intval', (array) old('id_siswa')), fn ($v) => $v > 0));
        if ($oldSiswaIds) {
            $oldSiswaRows = Siswa::with('kelas')->whereIn('id', $oldSiswaIds)->orderBy('nama')->get()
                ->map(fn (Siswa $s) => [
                    'id' => $s->id,
                    'nama' => $s->nama,
                    'nisn' => $s->nisn,
                    'id_kelas' => $s->id_kelas,
                    'nama_kelas' => $s->kelas?->nama_lengkap ?? '-',
                ])->values();
        }

        $jadwalQuery = JadwalPelajaran::with(['jamPelajaran', 'kelas', 'mapel', 'guru']);
        if ($tahunAktif) {
            $jadwalQuery->where('id_tahun_ajaran', $tahunAktif->id);
        }

        $jadwalOptions = $jadwalQuery->get()->map(fn (JadwalPelajaran $j) => [
            'id' => $j->id,
            'hari' => $j->hari,
            'jam_ke' => (int) ($j->jamPelajaran?->jam_ke ?? 0),
            'id_kelas' => $j->id_kelas,
            'nama_kelas' => $j->kelas?->nama_kelas ?? 'Tanpa Kelas',
            'mapel' => $j->mapel?->nama_mapel ?? '-',
            'guru' => $j->guru?->nama ?? '-',
        ])->values();

        // Integrasi Catatan Terlambat (input Satpam): siswa yang tercatat
        // terlambat hari ini & belum dikonfirmasi Guru Piket. Disisipkan sebagai
        // group "Siswa Terlambat" di bagian atas dropdown siswa (tab Masuk Kelas);
        // memilihnya mengisi form otomatis dari catatan keterlambatan di gerbang.
        $terlambatSaatIni = CatatanTerlambat::with(['siswa.kelas'])
            ->whereDate('tanggal', now()->toDateString())
            ->whereNull('dispensasi_id')
            ->where('is_approved_piket', false)
            ->orderBy('jam_masuk')
            ->get();

        $terlambatJson = $terlambatSaatIni->map(fn (CatatanTerlambat $c) => [
            'id' => (int) $c->id,
            'id_siswa' => (int) $c->id_siswa,
            'nama' => $c->siswa?->nama ?? '-',
            'nisn' => (string) ($c->siswa?->nisn ?? ''),
            'kelas' => $c->siswa?->kelas?->nama_lengkap ?? $c->siswa?->kelas?->nama_kelas ?? '-',
            'kelas_id' => (int) ($c->siswa?->id_kelas ?? 0),
            'jam_masuk' => $c->jam_masuk?->format('H:i'),
            'keterangan' => (string) ($c->keterangan ?? ''),
            'saran_jp' => $c->jam_masuk ? $this->sugestJpDariJamMasuk($c->jam_masuk) : null,
        ])->values()->all();

        return view('piket.dispensasi.create', compact(
            'dataSiswa', 'jadwalOptions', 'jamPelajaran', 'jamPelajaranPerHari',
            'kelasList', 'selectedKelas', 'oldSiswaRows', 'terlambatJson',
            'jamMasukDefault', 'jamPelajaranList', 'currentJp'
        ));
    }

    /**
     * API AJAX: master Jam Pelajaran untuk SATU tanggal dispen (kategori hari
     * Jumat vs Senin-Kamis). Dipakai dropdown 'Dari JP'/'Sampai JP' (Part 1) &
     * JP keluar/kembali/masuk agar rentang mengikuti jadwal tanggal yang dipilih,
     * bukan hanya kategori hari browser sekarang.
     */
    public function apiJamPelajaran(Request $request)
    {
        $this->authorizeGuruPiket();

        $tanggal = (string) $request->get('tanggal', now()->toDateString());
        try {
            $date = now()->parse($tanggal);
        } catch (\Throwable $e) {
            $date = now();
        }
        $kategori = $date->isFriday() ? 'Jumat' : 'Senin-Kamis';

        $data = JamPelajaran::where('kategori_hari', $kategori)
            ->whereNotNull('jam_ke')
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn (JamPelajaran $jp) => [
                'id'      => (int) $jp->id,
                'jam_ke'  => (int) $jp->jam_ke,
                'mulai'   => substr((string) $jp->jam_mulai, 0, 5),
                'selesai' => substr((string) $jp->jam_selesai, 0, 5),
            ])
            ->values();

        return response()->json([
            'error'   => false,
            'tanggal' => $date->toDateString(),
            'data'    => $data,
        ]);
    }

    /**
     * API AJAX: daftar siswa aktif pada satu kelas (cascading dropdown).
     * Query ringan: hanya id, nama, nisn untuk kelas terpilih (+ filter pencarian opsional).
     */
    public function siswaByKelas(Request $request)
    {
        $kelasId = (int) $request->query('kelas_id', 0);

        if ($kelasId <= 0 || ! Kelas::whereKey($kelasId)->exists()) {
            return response()->json([
                'error' => true,
                'message' => 'Kelas tidak valid.',
                'data' => [],
            ], 422);
        }

        $query = Siswa::where('id_kelas', $kelasId)
            ->where('status_siswa', 'Aktif');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nisn', 'like', '%'.$search.'%');
            });
        }

        $siswa = $query->orderBy('nama')->get(['id', 'nama', 'nisn']);

        return response()->json([
            'error' => false,
            'data' => $siswa->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'nisn' => $s->nisn,
            ]),
        ]);
    }

    /**
     * API AJAX: daftar siswa terlambat (catatan Satpam) pada satu tanggal yang
     * BELUM dikonfirmasi Guru Piket (`is_approved_piket = false` & belum
     * terhubung ke dispensasi). Dipakai quick-select tab "Masuk Kelas".
     */
    public function terlambatHariIni(Request $request)
    {
        $this->authorizeGuruPiket();

        $tanggal = $request->get('tanggal', now()->toDateString());

        $data = CatatanTerlambat::with(['siswa.kelas'])
            ->whereDate('tanggal', $tanggal)
            ->whereNull('dispensasi_id')
            ->where('is_approved_piket', false)
            ->orderBy('jam_masuk')
            ->get()
            ->map(fn (CatatanTerlambat $c) => [
                'id' => (int) $c->id,
                'id_siswa' => (int) $c->id_siswa,
                'nama' => $c->siswa?->nama ?? '-',
                'nisn' => (string) ($c->siswa?->nisn ?? ''),
                'kelas' => $c->siswa?->kelas?->nama_lengkap ?? $c->siswa?->kelas?->nama_kelas ?? '-',
                'kelas_id' => (int) ($c->siswa?->id_kelas ?? 0),
                'jam_masuk' => $c->jam_masuk?->format('H:i'),
                'keterangan' => (string) ($c->keterangan ?? ''),
                'saran_jp' => $c->jam_masuk ? $this->sugestJpDariJamMasuk($c->jam_masuk) : null,
            ])
            ->values();

        return response()->json(['error' => false, 'data' => $data]);
    }

    /**
     * Konversi string waktu (atau Carbon) ke menit sejak tengah malam.
     */
    protected function menitKeInt($waktu): int
    {
        $t = substr((string) $waktu, 0, 5);

        if (! str_contains($t, ':')) {
            return 0;
        }

        return ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
    }

    /**
     * Saran JP "Boleh Masuk" dari jam kedatangan di gerbang:
     * - Terlambat di tengah JP berlangsung -> JP berikutnya (siswa mengikuti
     *   KBM mulai JP setelah JP yang terlewat).
     * - Datang sebelum JP pertama -> JP pertama.
     * - Datang di sela-sela JP / setelah semua JP -> JP pertama yang masih
     *   bisa diikuti.
     */
    protected function sugestJpDariJamMasuk($jamMasuk): ?int
    {
        $menit = $jamMasuk instanceof CarbonInterface
            ? $jamMasuk->format('H') * 60 + (int) $jamMasuk->format('i')
            : $this->menitKeInt($jamMasuk);

        if ($menit <= 0) {
            return null;
        }

        $jps = JamPelajaran::whereNotNull('jam_ke')
            ->whereNotNull('jam_mulai')
            ->orderBy('jam_mulai')
            ->get();

        foreach ($jps as $jp) {
            $mulai = $this->menitKeInt($jp->jam_mulai);
            $selesai = $jp->jam_selesai ? $this->menitKeInt($jp->jam_selesai) : $mulai + 45;

            if ($menit >= $mulai && $menit < $selesai) {
                return (int) ($jps->firstWhere('jam_ke', (int) $jp->jam_ke + 1)?->jam_ke ?? $jp->jam_ke);
            }
        }

        foreach ($jps as $jp) {
            if ($menit <= $this->menitKeInt($jp->jam_mulai)) {
                return (int) $jp->jam_ke;
            }
        }

        return null;
    }

    /**
     * JP yang sedang / segera berlangsung berdasarkan waktu sistem sekarang
     * (acuan default dropdown "Boleh Masuk Mulai JP Ke-"). Urutan prioritas:
     * JP aktif -> JP berikutnya -> JP terakhir di hari tersebut.
     */
    protected function jamKeSaranSekarang(): ?int
    {
        $kategoriHari = now()->isFriday() ? 'Jumat' : 'Senin-Kamis';
        $nowTime = now()->format('H:i:s');

        $templates = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->whereNotNull('jam_ke')
            ->orderBy('jam_mulai')
            ->get();

        $active = $templates->first(fn ($t) => $t->jam_mulai <= $nowTime && $nowTime < $t->jam_selesai);

        if ($active) {
            return (int) $active->jam_ke;
        }

        $next = $templates->where('jenis', 'kbm')->first(fn ($t) => $t->jam_mulai > $nowTime);

        if ($next) {
            return (int) $next->jam_ke;
        }

        $last = $templates->last();

        return $last?->jam_ke !== null ? (int) $last->jam_ke : null;
    }

    /**
     * Buat dispensasi baru: Guru Piket mengisi detail & langsung menyetujui (ACC).
     *
     * - Satu siswa  -> alur lama: status DISETUJUI, redirect ke halaman TTD siswa.
     * - Banyak siswa -> transaksi kolektif (header DispensasiKolektif + baris anak
     *   DispensasiSiswa), setiap siswa sudah membawa TTD digital hasil wizard.
     */
    public function store(Request $request)
    {
        $this->authorizeGuruPiket();

        // Normalisasi: sampai_jp & jam_keluar_jp diturunkan dari dari_jp bila tidak dikirim eksplisit.
        if ($request->filled('dari_jp') && !$request->filled('sampai_jp')) {
            $request->merge(['sampai_jp' => $request->input('dari_jp')]);
        }
        if ($request->filled('dari_jp') && !$request->filled('jam_keluar_jp')) {
            // Cari jam_ke dari id JP yang dipilih
            $jpMulai = \App\Models\JamPelajaran::find($request->input('dari_jp'));
            if ($jpMulai) {
                $request->merge(['jam_keluar_jp' => $jpMulai->jam_ke]);
            }
        }

        $tipe = $request->input('tipe_dispen') === DispensasiSiswa::TIPE_MASUK
            ? DispensasiSiswa::TIPE_MASUK
            : DispensasiSiswa::TIPE_KELUAR;

        $idSiswaRaw = $request->input('id_siswa');
        $idSiswaList = array_values(array_filter(
            array_map('intval', (array) $idSiswaRaw),
            fn ($v) => $v > 0
        ));

        // catatan_terlambat_id[] disejajarkan dengan id_siswa[] (baris siswa terisi
        // saja yang mengirim; baris kosong di-strip frontend saat submit). Dipakai
        // integrasi Dispen Masuk Kelas dengan catatan keterlambatan Satpam.
        $catatanIds = array_values(array_map(
            'intval',
            (array) $request->input('catatan_terlambat_id')
        ));

        if (empty($idSiswaList)) {
            return back()->withInput()->withErrors(['id_siswa' => 'Pilih minimal satu siswa untuk dispensasi.']);
        }

        if (count($idSiswaList) !== count(array_unique($idSiswaList))) {
            return back()->withInput()->withErrors(['id_siswa' => 'Tidak boleh ada siswa yang sama dipilih lebih dari sekali.']);
        }

        // Pastikan semua siswa terdaftar & berstatus aktif.
        $validIds = Siswa::whereIn('id', $idSiswaList)
            ->where('status_siswa', 'Aktif')
            ->pluck('id')
            ->all();

        if (count($validIds) !== count($idSiswaList)) {
            return back()->withInput()->withErrors(['id_siswa' => 'Salah satu siswa yang dipilih tidak ditemukan atau tidak aktif.']);
        }

        if (count($idSiswaList) === 1) {
            return $this->storeSingle($request, $tipe, (int) $idSiswaList[0], $catatanIds[0] ?? null);
        }

        // Normalisasi panjang array catatan agar tetap sejajar dengan idSiswaList
        // bila frontend tidak mengirim nilai untuk sebagian baris.
        $catatanIds = array_slice(array_pad($catatanIds, count($idSiswaList), 0), 0, count($idSiswaList));

        return $this->storeKolektif($request, $tipe, $idSiswaList, $catatanIds);
    }

    /**
     * Normalisasi ID siswa dari input form (string tunggal / array).
     */
    protected function storeSingle(Request $request, string $tipe, int $idSiswaId, ?int $catatanTerlambatId = null)
    {
        if ($tipe === DispensasiSiswa::TIPE_MASUK) {
            $validated = $request->validate([
                'tanggal' => 'required|date',
                'jam_masuk_jp' => 'required|integer|min:1|max:20',
                'alasan_kategori' => 'required|string|max:100',
                'alasan_detail' => 'required|string|max:500',
                'ttd_guru' => 'sometimes|string|max:150000',
                'ttd_piket' => 'sometimes|string|max:150000',
            ], [
                'tanggal.required' => 'Tanggal dispen wajib diisi.',
                'tanggal.date' => 'Format tanggal tidak valid.',
                'jam_masuk_jp.required' => 'Pilih JP saat siswa boleh masuk kelas.',
                'jam_masuk_jp.integer' => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.min' => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.max' => 'Nomor JP masuk terlalu besar.',
                'alasan_kategori.required' => 'Kategori alasan wajib dipilih.',
                'alasan_kategori.string' => 'Kategori alasan tidak valid.',
                'alasan_detail.required' => 'Detail / catatan alasan wajib diisi.',
                'alasan_detail.max' => 'Detail alasan maksimal :max karakter.',
                'ttd_guru.max' => 'Ukuran tanda tangan Guru Piket terlalu besar.',
                'ttd_piket.max' => 'Ukuran tanda tangan Guru Piket terlalu besar.',
            ]);

            $alasan = trim((string) $validated['alasan_kategori']);
            if (! empty(trim((string) ($validated['alasan_detail'] ?? '')))) {
                $alasan .= ' — '.trim($validated['alasan_detail']);
            }

            $jamKe = null;
            $jamMasukJp = (int) $validated['jam_masuk_jp'];
            $jamKeluarJp = null;
            $idJadwal = null;
            $idGuru = null;
        } else {
            $validated = $request->validate([
                'tanggal' => 'required|date',
                'jam_ke' => 'required|array|min:1',
                'jam_ke.*' => 'integer|min:1|max:20',
                'jam_keluar_jp' => 'nullable|integer|min:1|max:20',
                'kembali_hari_ini' => 'sometimes|nullable',
                'alasan' => 'required|string|max:500',
                'id_jadwal' => 'nullable|exists:jadwal_pelajaran,id',
                'ttd_guru' => 'sometimes|string|max:150000',
                'ttd_piket' => 'sometimes|string|max:150000',
            ], [
                'tanggal.required' => 'Tanggal dispen wajib diisi.',
                'tanggal.date' => 'Format tanggal tidak valid.',
                'jam_ke.required' => 'Pilih minimal satu jam pelajaran.',
                'jam_ke.array' => 'Format jam pelajaran tidak valid.',
                'jam_ke.min' => 'Pilih minimal satu jam pelajaran.',
                'jam_ke.*.integer' => 'Nomor jam pelajaran tidak valid.',
                'jam_ke.*.min' => 'Nomor jam pelajaran tidak valid.',
                'jam_ke.*.max' => 'Nomor jam pelajaran terlalu besar.',
                'jam_keluar_jp.integer' => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.min' => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.max' => 'Nomor JP keluar terlalu besar.',
                'alasan.required' => 'Alasan kegiatan dispen wajib diisi.',
                'alasan.max' => 'Alasan maksimal :max karakter.',
                'id_jadwal.exists' => 'Jadwal pelajaran yang dipilih tidak valid.',
                'ttd_guru.max' => 'Ukuran tanda tangan Guru Piket terlalu besar.',
                'ttd_piket.max' => 'Ukuran tanda tangan Guru Piket terlalu besar.',
            ]);

            // Mapel / Guru Mapel yang ditinggalkan (opsional).
            $idJadwal = ! empty($validated['id_jadwal']) ? (int) $validated['id_jadwal'] : null;
            $idGuru = $idJadwal
                ? (int) (JadwalPelajaran::find($idJadwal)?->id_guru ?: 0) ?: null
                : null;

            $jamKe = collect($validated['jam_ke'])
                ->map(fn ($j) => (int) $j)
                ->filter(fn ($j) => $j > 0)
                ->sort()
                ->values()
                ->implode(',');

            $alasan = $validated['alasan'];
            $jamMasukJp = null;
            $jamKeluarJp = ! empty($validated['jam_keluar_jp']) ? (int) $validated['jam_keluar_jp'] : null;
        }

        // Part 2 - Penanda Kembali Hari Ini (boolean).
        $jamKembaliJp = null;
        $tidakKembali = true;
        if ($tipe === DispensasiSiswa::TIPE_KELUAR) {
            $tidakKembali = ! $request->boolean('kembali_hari_ini');
        }

        $ttdGuru = $this->normalizeTtd(
            $request->input('ttd_guru') ?? $request->input('ttd_piket')
        );
        if (! $ttdGuru) {
            return back()->withErrors(['ttd_guru' => 'Tanda tangan Guru Piket wajib digambar terlebih dahulu.']);
        }

        $dispensasi = DispensasiSiswa::create([
            'id_siswa' => $idSiswaId,
            'id_guru_piket' => Auth::id(),
            'id_jadwal' => $idJadwal,
            'id_guru' => $idGuru,
            'tanggal' => $validated['tanggal'],
            'tipe_dispen' => $tipe,
            'jam_ke' => $jamKe,
            'jam_keluar_jp' => $jamKeluarJp,
            'jam_masuk_jp' => $jamMasukJp,
            'jam_kembali_jp' => $jamKembaliJp,
            'tidak_kembali_hari_ini' => $tidakKembali,
            'alasan' => $alasan,
            'status' => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'ttd_guru' => $ttdGuru,
            'approval_token' => Str::random(32),
        ]);

        $this->linkCatatanTerlambat($dispensasi, $catatanTerlambatId);

        if ($tipe !== DispensasiSiswa::TIPE_MASUK) {
            $dispensasi->terapkanKeAbsensi();
        }

        return redirect()->route('piket.dispensasi.ttd', $dispensasi->id)
            ->with('success', 'Dispensasi telah disetujui (ACC) oleh Guru Piket. Silakan lengkapi Tanda Tangan Siswa sebagai konfirmasi akhir.');
    }

    /**
     * Simpan pengajuan dispensasi kolektif (rombongan):
     * 1 parent transaction (DispensasiKolektif) + 1 baris anak DispensasiSiswa
     * per siswa. Tanda tangan digital masing-masing siswa (hasil wizard TTD
     * berurutan) ikut tersimpan pada baris anak.
     */
    protected function storeKolektif(Request $request, string $tipe, array $idSiswaList, array $catatanTerlambatIds = [])
    {
        $validated = $tipe === DispensasiSiswa::TIPE_MASUK
            ? $request->validate([
                'tanggal' => 'required|date',
                'jam_masuk_jp' => 'required|integer|min:1|max:20',
                'alasan_kategori' => 'required|string|max:100',
                'alasan_detail' => 'required|string|max:500',
                'ttd_guru' => 'sometimes|string|max:150000',
                'ttd_piket' => 'sometimes|string|max:150000',
                'ttd_siswa' => 'required|array|min:1',
            ], [
                'tanggal.required' => 'Tanggal dispen wajib diisi.',
                'jam_masuk_jp.required' => 'Pilih JP saat siswa boleh masuk kelas.',
                'jam_masuk_jp.integer' => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.min' => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.max' => 'Nomor JP masuk terlalu besar.',
                'alasan_kategori.required' => 'Kategori alasan wajib dipilih.',
                'alasan_detail.required' => 'Detail / catatan alasan wajib diisi.',
                'alasan_detail.max' => 'Detail alasan maksimal :max karakter.',
                'ttd_siswa.required' => 'Tanda tangan digital wajib digambar untuk setiap siswa.',
                'ttd_siswa.array' => 'Format tanda tangan siswa tidak valid.',
                'ttd_siswa.min' => 'Tanda tangan digital wajib digambar untuk setiap siswa.',
            ])
            : $request->validate([
                'tanggal' => 'required|date',
                'jam_ke' => 'required|array|min:1',
                'jam_ke.*' => 'integer|min:1|max:20',
                'jam_keluar_jp' => 'nullable|integer|min:1|max:20',
                'kembali_hari_ini' => 'sometimes|nullable',
                'alasan' => 'required|string|max:500',
                'id_jadwal' => 'nullable|exists:jadwal_pelajaran,id',
                'ttd_guru' => 'sometimes|string|max:150000',
                'ttd_piket' => 'sometimes|string|max:150000',
                'ttd_siswa' => 'required|array|min:1',
            ], [
                'tanggal.required' => 'Tanggal dispen wajib diisi.',
                'jam_ke.required' => 'Pilih minimal satu jam pelajaran.',
                'jam_ke.array' => 'Format jam pelajaran tidak valid.',
                'jam_ke.min' => 'Pilih minimal satu jam pelajaran.',
                'jam_ke.*.integer' => 'Nomor jam pelajaran tidak valid.',
                'jam_ke.*.min' => 'Nomor jam pelajaran tidak valid.',
                'jam_ke.*.max' => 'Nomor jam pelajaran terlalu besar.',
                'jam_keluar_jp.integer' => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.min' => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.max' => 'Nomor JP keluar terlalu besar.',
                'alasan.required' => 'Alasan kegiatan dispen wajib diisi.',
                'alasan.max' => 'Alasan maksimal :max karakter.',
                'id_jadwal.exists' => 'Jadwal pelajaran yang dipilih tidak valid.',
                'ttd_siswa.required' => 'Tanda tangan digital wajib digambar untuk setiap siswa.',
                'ttd_siswa.array' => 'Format tanda tangan siswa tidak valid.',
                'ttd_siswa.min' => 'Tanda tangan digital wajib digambar untuk setiap siswa.',
            ]);

        if ($tipe === DispensasiSiswa::TIPE_MASUK) {
            $alasan = trim((string) $validated['alasan_kategori']);
            if (! empty(trim((string) ($validated['alasan_detail'] ?? '')))) {
                $alasan .= ' — '.trim($validated['alasan_detail']);
            }

            $jamKe = null;
            $jamMasukJp = (int) $validated['jam_masuk_jp'];
            $jamKeluarJp = null;
            $idJadwal = null;
            $idGuru = null;
        } else {
            $idJadwal = ! empty($validated['id_jadwal']) ? (int) $validated['id_jadwal'] : null;
            $idGuru = $idJadwal
                ? (int) (JadwalPelajaran::find($idJadwal)?->id_guru ?: 0) ?: null
                : null;

            $jamKe = collect($validated['jam_ke'])
                ->map(fn ($j) => (int) $j)
                ->filter(fn ($j) => $j > 0)
                ->sort()
                ->values()
                ->implode(',');

            $alasan = $validated['alasan'];
            $jamMasukJp = null;
            $jamKeluarJp = ! empty($validated['jam_keluar_jp']) ? (int) $validated['jam_keluar_jp'] : null;
        }

        $jamKembaliJp = null;
        $tidakKembali = true;
        if ($tipe === DispensasiSiswa::TIPE_KELUAR) {
            $tidakKembali = ! $request->boolean('kembali_hari_ini');
        }

        $ttdGuru = $this->normalizeTtd(
            $request->input('ttd_guru') ?? $request->input('ttd_piket')
        );
        if (! $ttdGuru) {
            return back()->withErrors(['ttd_guru' => 'Tanda tangan Guru Piket wajib digambar terlebih dahulu.']);
        }

        // TTD digital setiap siswa (hasil wizard) — posisi sejajar dengan id_siswa[].
        $ttdSiswaRaw = (array) $request->input('ttd_siswa');
        $ttdSiswaMap = [];
        foreach ($idSiswaList as $i => $idSiswa) {
            $ttd = $this->normalizeTtd($ttdSiswaRaw[$i] ?? null);
            if (! $ttd) {
                return back()->withInput()->withErrors([
                    'ttd_siswa' => 'Tanda tangan digital siswa ke-'.($i + 1).' harus digambar terlebih dahulu.',
                ]);
            }
            $ttdSiswaMap[$idSiswa] = $ttd;
        }

        $kolektif = DB::transaction(function () use ($tipe, $idSiswaList, $catatanTerlambatIds, $validated, $jamKe, $jamMasukJp, $jamKeluarJp, $jamKembaliJp, $tidakKembali, $alasan, $idJadwal, $idGuru, $ttdGuru, $ttdSiswaMap) {
            $parent = DispensasiKolektif::create([
                'id_guru_piket' => Auth::id(),
                'id_jadwal' => $idJadwal,
                'id_guru' => $idGuru,
                'tanggal' => $validated['tanggal'],
                'tipe_dispen' => $tipe,
                'jam_ke' => $jamKe,
                'jam_keluar_jp' => $jamKeluarJp,
                'jam_masuk_jp' => $jamMasukJp,
                'jam_kembali_jp' => $jamKembaliJp,
                'tidak_kembali_hari_ini' => $tidakKembali,
                'alasan' => $alasan,
                'status' => DispensasiSiswa::STATUS_DISETUJUI,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'ttd_guru' => $ttdGuru,
                'approval_token' => Str::random(32),
            ]);

            foreach ($idSiswaList as $i => $idSiswa) {
                $dispen = DispensasiSiswa::create([
                    'dispensasi_kolektif_id' => $parent->id,
                    'id_siswa' => $idSiswa,
                    'id_guru_piket' => Auth::id(),
                    'id_jadwal' => $idJadwal,
                    'id_guru' => $idGuru,
                    'tanggal' => $validated['tanggal'],
                    'tipe_dispen' => $tipe,
                    'jam_ke' => $jamKe,
                    'jam_keluar_jp' => $jamKeluarJp,
                    'jam_masuk_jp' => $jamMasukJp,
                    'jam_kembali_jp' => $jamKembaliJp,
                    'tidak_kembali_hari_ini' => $tidakKembali,
                    'alasan' => $alasan,
                    'status' => DispensasiSiswa::STATUS_DISETUJUI,
                    'approved_at' => now(),
                    'approved_by' => Auth::id(),
                    'ttd_guru' => $ttdGuru,
                    'ttd_siswa' => $ttdSiswaMap[$idSiswa],
                    'approval_token' => Str::random(32),
                ]);

                $this->linkCatatanTerlambat($dispen, $catatanTerlambatIds[$i] ?? null);

                if ($tipe !== DispensasiSiswa::TIPE_MASUK) {
                    $dispen->terapkanKeAbsensi();
                }
            }

            return $parent;
        });

        return redirect()->route('piket.dispensasi.kolektif.surat', $kolektif->id)
            ->with('success', 'Dispensasi kolektif untuk '.count($idSiswaList).' siswa berhasil disimpan beserta tanda tangan digital masing-masing siswa.');
    }

    /**
     * Normalisasi tanda tangan digital (data URL PNG) dari canvas; mengembalikan
     * null bila bukan data URL PNG yang valid / kosong.
     */
    protected function normalizeTtd($raw): ?string
    {
        $value = trim((string) ($raw ?? ''));

        return preg_match('/^data:image\/png;base64,/i', $value) ? $value : null;
    }

    /**
     * Hubungkan surat Dispensasi Masuk Kelas ke catatan keterlambatan Satpam
     * (is_approved_piket = true). Hanya berlaku untuk tipe masuk, catatan yang
     * cocok (siswa + tanggal sama, belum terhubung ke dispensasi lain), dan
     * id catatan yang valid.
     */
    protected function linkCatatanTerlambat(DispensasiSiswa $dispen, ?int $catatanTerlambatId = null): void
    {
        if (! $dispen->isTipeMasuk() || ! $catatanTerlambatId) {
            return;
        }

        $catatan = CatatanTerlambat::whereKey($catatanTerlambatId)
            ->where('id_siswa', $dispen->id_siswa)
            ->whereDate('tanggal', $dispen->tanggal)
            ->whereNull('dispensasi_id')
            ->where('is_approved_piket', false)
            ->first();

        if ($catatan) {
            $catatan->update([
                'is_approved_piket' => true,
                'dispensasi_id' => $dispen->id,
            ]);
        }
    }

    /**
     * Halaman Surat Dispen (standalone, tanpa sidebar/navbar), siap cetak / PDF.
     * Bisa dibuka dalam status apa pun; status approval tampil jelas pada surat.
     */
    public function showSurat($id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = $this->findDispensasiForView($id);

        $allowed = $user->isPiketHariIni()
            || $user->isPetugasIt()
            || (int) $dispensasi->approved_by === (int) $user->id
            || (int) $dispensasi->id_guru_piket === (int) $user->id
            || $user->isWakaKesiswaan()
            || ($dispensasi->wakaKesiswaan && (int) $dispensasi->wakaKesiswaan->id === (int) $user->id)
            || $user->isGuru()
            || $user->isAdmin();

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melihat surat dispen ini.');

        $piket = $dispensasi->guruPiket ?? $user;

        // Tipe "Masuk Kelas" => tampilkan surat/nota masuk (tanpa TTD Waka Kesiswaan).
        if ($dispensasi->isTipeMasuk()) {
            $jamMasukDetail = $dispensasi->jam_masuk_jp
                ? JamPelajaran::where('jam_ke', $dispensasi->jam_masuk_jp)->orderBy('jam_mulai')->first()
                : null;

            return view('piket.dispensasi.surat_masuk', compact('dispensasi', 'piket', 'jamMasukDetail'));
        }

        // Resolusi identitas Waka Kesiswaan untuk TTD di surat:
        // 1. Penandatangan tersimpan (kolom waka_kesiswaan_id) — paling akurat,
        //    karena menyimpan persis user Waka Kesiswaan yang menggambar TTD
        //    (via approval publik ataupun portal Waka Kesiswaan).
        // 2. Jika belum / kolom kosong tapi ttd_waka ada & approver benar-benar
        //    Waka Kesiswaan (isWakaKesiswaan) — tampilkan user tsb (nama + NIP).
        //    Catatan: approved_by TIDAK selalu Waka (alur piket menyimpan id guru
        //    piket pembuat; alur waka kurikulum menyimpan waka kurikulum).
        // 3. Fallback -> user yang ditunjuk (role admin + sub_role 'waka_kesiswaan').
        // 4. Fallback terakhir -> setting nama_waka_kesiswaan & nip_waka_kesiswaan.
        // Tetap bukan auth()->user(), bukan user pembuat/TU.
        $waka = null;

        if (! empty($dispensasi->ttd_waka) && $dispensasi->wakaKesiswaan) {
            $waka = $dispensasi->wakaKesiswaan;
        }

        if (! $waka
            && ! empty($dispensasi->ttd_waka)
            && $dispensasi->approver
            && $dispensasi->approver->isWakaKesiswaan()) {
            $waka = $dispensasi->approver;
        }

        if (! $waka) {
            $waka = User::wakaKesiswaan();
        }

        $wakaNama = null;
        $wakaNip = null;

        if ($waka) {
            $wakaNama = trim((string) $waka->nama);
            $wakaNip = $this->validNip($waka->nip) ? trim((string) $waka->nip) : null;
        }

        if (empty($wakaNama)) {
            $setting = PengaturanJadwal::getSetting();
            $namaSetting = trim((string) ($setting->nama_waka_kesiswaan ?? ''));
            if ($namaSetting !== '') {
                $wakaNama = $namaSetting;
                $wakaNip = $this->validNip($setting->nip_waka_kesiswaan ?? null)
                    ? trim((string) $setting->nip_waka_kesiswaan)
                    : null;
            }
        }

        $jamKeluarDetail = $dispensasi->jam_keluar_jp
            ? JamPelajaran::where('jam_ke', $dispensasi->jam_keluar_jp)->orderBy('jam_mulai')->first()
            : null;

        return view('piket.dispensasi.surat', compact(
            'dispensasi', 'piket', 'wakaNama', 'wakaNip', 'jamKeluarDetail'
        ));
    }

    /**
     * Tampilkan surat dispensasi kolektif (rombongan): satu kop surat berisi
     * daftar siswa yang di-dispensasi lengkap dengan TTD digital masing-masing.
     */
    public function showSuratKolektif($id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $kolektif = $this->findKolektifForView((int) $id);

        $allowed = $user->isPiketHariIni()
            || $user->isPetugasIt()
            || (int) $kolektif->approved_by === (int) $user->id
            || (int) $kolektif->id_guru_piket === (int) $user->id
            || $user->isWakaKesiswaan()
            || $user->isGuru()
            || $user->isAdmin();

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melihat surat dispen rombongan ini.');

        return view('piket.dispensasi.surat_kolektif', compact('kolektif', 'user'));
    }

    /**
     * NIP dianggap valid (bukan dummy/placeholder) jika berisi minimal 6 digit
     * dan tidak semua nol. Kolom tanpa nilai atau nilai placeholder disembunyikan.
     */
    protected function validNip(?string $nip): bool
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $nip);

        return strlen($digits) >= 6 && ! preg_match('/^0+$/', $digits);
    }

    /**
     * Resolve dispensasi untuk kebutuhan TAMPILAN surat.
     *
     * Pengguna yang sedang impersonasi Waka Kesiswaan membuka link surat dari
     * portal yang menampilkan record real + testing sekaligus. Tanpa bypass, ID
     * record real yang disembunyikan TestingDataScope (mis. ID kecil dari
     * produksi) akan berakhir 404 — akar masalah bug "Lihat Surat".
     *
     * Non-impersonasi (termasuk IT/QA biasa) tetap scoped penuh, dan bingung
     * id hilang → 404 jelas.
     */
    protected function findDispensasiForView(int $id): DispensasiSiswa
    {
        $user = Auth::user();
        $bypass = $user instanceof User
            && $user->hasActiveRole()
            && $user->activeRole() === 'waka_kesiswaan';

        try {
            $query = $bypass
                ? DispensasiSiswa::withoutGlobalScope(TestingDataScope::class)->with($this->crossScopeRelations())
                : DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver', 'wakaKesiswaan']);

            return $query->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Surat dispensasi tidak ditemukan atau tidak dapat diakses.');
        }
    }

    /**
     * Resolve dispensasi untuk MUTASI (isi TTD, batalkan) tetap memakai scope
     * sesuai bucket user — hanya frontend view yang boleh bypass — agar isolasi
     * data testing tidak bocor pada penyimpanan. 404 diberi pesan yang jelas.
     */
    protected function findDispensasiForAction(int $id): DispensasiSiswa
    {
        try {
            return DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Surat dispensasi tidak ditemukan atau tidak dapat diakses.');
        }
    }

    /**
     * Relasi yang ikut di-resolve tanpa TestingDataScope saat memberangkatkan
     * bypass — siswa/kelas/guru/approver dari bucket lain tetap ter-resolve,
     * agar surat record real yang dibuka IT pun menampilkan datanya dengan benar.
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

    /**
     * Resolve induk dispensasi kolektif untuk tampilan surat dengan aturan scope
     * yang sama seperti findDispensasiForView:
     *
     * - User biasa (guru piket, dst): pakai IS / global scope TestingDataScope
     *   apa adanya — IT/QA hanya melihat data testing, non-IT hanya data real.
     *   TANPA menambahkan where manual, karena menambahkan orWhere(is_testing_data)
     *   justru membatalkan bucket scope dan memaksa record real/testing yang
     *   seharusnya tampil jadi 404 (mismatch bucket IT vs non-IT).
     * - Impersonasi Waka Kesiswaan: bypass TestingDataScope agar kolektif real
     *   yang dibuka dari portal Waka ikut tampil (siswaItems ter-resolve tanpa
     *   scope dari bucket lain).
     */
    protected function findKolektifForView(int $id): DispensasiKolektif
    {
        $user = Auth::user();
        $bypass = $user instanceof User
            && $user->hasActiveRole()
            && $user->activeRole() === 'waka_kesiswaan';

        $cross = function ($query) {
            $query->withoutGlobalScope(TestingDataScope::class);
        };

        try {
            $query = $bypass
                ? DispensasiKolektif::withoutGlobalScope(TestingDataScope::class)->with([
                    'guruPiket' => $cross,
                    'approver' => $cross,
                    'siswaItems' => $cross,
                    'siswaItems.siswa.kelas' => $cross,
                ])
                : DispensasiKolektif::with([
                    'guruPiket',
                    'approver',
                    'siswaItems',
                    'siswaItems.siswa.kelas',
                ]);

            return $query->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            abort(404, 'Surat dispensasi rombongan tidak ditemukan atau tidak dapat diakses.');
        }
    }

    /**
     * Halaman pengisian Tanda Tangan Siswa (Pemohon) sebagai konfirmasi akhir
     * setelah dispensasi disetujui (ACC) oleh Guru Piket.
     */
    public function showTtd($id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = $this->findDispensasiForAction($id);

        $allowed = $user->isPiketHariIni()
            || $user->isPetugasIt()
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melengkapi surat dispen ini.');

        return view('piket.dispensasi.ttd', compact('dispensasi'));
    }

    /**
     * Simpan Tanda Tangan Siswa (Pemohon) pada surat dispen yang sudah disetujui.
     */
    public function saveTtd(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = $this->findDispensasiForAction($id);

        // Guard: surat data testing hanya dapat dilengkapi oleh IT/QA.
        $this->authorizeTestingMutation($dispensasi);

        $allowed = $user->isPiketHariIni()
            || $user->isPetugasIt()
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melengkapi surat dispen ini.');

        $validated = $request->validate([
            'ttd_siswa' => 'required|string|max:150000',
        ]);

        $ttdBase64 = preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_siswa']))
            ? trim($validated['ttd_siswa'])
            : null;

        if (! $ttdBase64) {
            return back()->with('error', 'Tanda tangan siswa wajib diisi.');
        }

        $dispensasi->update(['ttd_siswa' => $ttdBase64]);

        return redirect()->route('piket.dispensasi.surat', $dispensasi->id)
            ->with('success', 'Tanda tangan siswa berhasil disimpan. Surat dispensasi kini lengkap dan sah.');
    }

    /**
     * Pembatalan Dispensasi wajib ditandatangani (TTD) oleh siswa.
     * Surat yang sudah Kadaluarsa / Ditolak / Dibatalkan / sudah "Siswa Out"
     * tidak dapat dibatalkan lagi.
     */
    public function pembatalanStore(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = $this->findDispensasiForAction($id);

        // Guard: surat data testing hanya dapat dibatalkan oleh IT/QA.
        $this->authorizeTestingMutation($dispensasi);

        $allowed = $user->isPiketHariIni()
            || $user->isPetugasIt()
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang membatalkan surat dispen ini.');

        abort_unless($dispensasi->isBisaDibatalkan(), 422, 'Surat dispensasi ini tidak dapat dibatalkan (sudah keluar / ditolak / kadaluarsa / dibatalkan).');

        $validated = $request->validate([
            'ttd_pembatalan' => 'required|string|max:150000',
        ], [
            'ttd_pembatalan.required' => 'Tanda tangan pembatalan siswa wajib diisi.',
            'ttd_pembatalan.max' => 'Ukuran tanda tangan pembatalan terlalu besar.',
        ]);

        $ttdPembatalan = preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_pembatalan']))
            ? trim($validated['ttd_pembatalan'])
            : null;

        if (! $ttdPembatalan) {
            return back()->with('error', 'Siswa wajib menandatangani canvas pembatalan terlebih dahulu.');
        }

        // Tarik status "Dispen" pada baris absensi jurnal yang dihasilkan
        // otomatis oleh surat ini (dikembalikan ke status sebelum dispensasi).
        $dicabut = $dispensasi->cabutDariAbsensi();

        $dispensasi->update([
            'status' => DispensasiSiswa::STATUS_DIBATALKAN,
            'ttd_pembatalan' => $ttdPembatalan,
            'dibatalkan_at' => now(),
            'dibatalkan_by' => $user->id,
        ]);

        return redirect()->route('piket.dispensasi.index', ['tanggal' => $dispensasi->tanggal?->toDateString()])
            ->with('success', 'Dispensasi '.$dispensasi->nomor_surat.' dibatalkan dengan tanda tangan siswa. '
                .($dicabut > 0 ? $dicabut.' baris absensi dispen dicabut dari jurnal.' : 'Absensi jurnal sudah selaras.'));
    }

    /**
     * Halaman approval publik untuk Waka Kesiswaan tanpa login.
     *
     * Token bisa berasal dari:
     *  - dispensasi tunggal   (dispensasi_siswa.approval_token)
     *  - dispensasi kolektif  (dispensasi_kolektif.approval_token)
     */
    public function publicApproveView($token)
    {
        Log::info('Public approval: checking token', ['token' => $token]);

        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->where('approval_token', $token)
            ->first();

        // Kolektif: token milik induk (dispensasi_kolektif). Ambil baris siswa
        // pertama sebagai perwakilan info; seluruh rombongan ikut ditandatangani.
        $kolektif = null;
        if (! $dispensasi) {
            $kolektif = DispensasiKolektif::with(['siswaItems.siswa.kelas'])
                ->where('approval_token', $token)
                ->first();

            if ($kolektif) {
                $dispensasi = $kolektif->siswaItems->first();
                Log::info('Public approval: token milik dispensasi kolektif', [
                    'token' => $token,
                    'kolektif_id' => $kolektif->id,
                    'jumlah_siswa' => $kolektif->siswaItems->count(),
                ]);
            }
        }

        // Daftar user Waka Kesiswaan aktif untuk dropdown "Pilih Waka Kesiswaan".
        $wakaList = User::wakaKesiswaanList();

        // Auto-detect: bila user yang sedang login adalah Waka Kesiswaan,
        // dropdown dikunci ke user tersebut (tidak bisa diganti).
        $authUser = Auth::user();
        $loggedInWakaId = ($authUser instanceof User && $authUser->isWakaKesiswaan())
            ? (int) $authUser->id
            : null;

        $data = [
            'token' => $token,
            'wakaList' => $wakaList,
            'loggedInWakaId' => $loggedInWakaId,
            'kolektif' => $kolektif,
        ];

        if (! $dispensasi) {
            return view('public.dispen-approval', array_merge($data, [
                'dispensasi' => null,
                'invalid' => true,
            ]));
        }

        if ($dispensasi->sudahDitandatanganiWaka()) {
            return view('public.dispen-approval', array_merge($data, [
                'dispensasi' => $dispensasi,
                'invalid' => false,
                'alreadySigned' => true,
            ]));
        }

        return view('public.dispen-approval', array_merge($data, [
            'dispensasi' => $dispensasi,
            'invalid' => false,
            'alreadySigned' => false,
        ]));
    }

    /**
     * Simpan tanda tangan Waka Kesiswaan untuk approval publik.
     */
    public function publicApproveStore(Request $request, $token)
    {
$dispensasi = DispensasiSiswa::where('approval_token', $token)->first();

// Cek juga di DispensasiKolektif (rombongan).
$kolektif = DispensasiKolektif::where('approval_token', $token)->first();
if ($kolektif) {
    $dispensasi = $kolektif->siswaItems->first();
}

if (! $dispensasi) {
            return redirect()->route('dispen.approval.show', $token)
                ->with('error', 'Token approval dispensasi tidak valid atau sudah kedaluwarsa.');
        }

        // Guard: surat data testing tidak dapat ditandatangani oleh non-IT.
        $this->authorizeTestingMutation($dispensasi);

        if (! empty($dispensasi->ttd_waka) || $dispensasi->status === DispensasiSiswa::STATUS_APPROVED) {
            return redirect()->route('dispen.approval.show', $token)
                ->with('info', 'Sudah Ditandatangani');
        }

        $validated = $request->validate([
            'ttd_waka' => 'required|string|max:150000',
            'waka_kesiswaan_id' => 'required|integer|exists:users,id',
        ], [
            'ttd_waka.required' => 'Tanda tangan Waka Kesiswaan wajib diisi.',
            'ttd_waka.max' => 'Ukuran tanda tangan terlalu besar.',
            'waka_kesiswaan_id.required' => 'Silakan pilih Waka Kesiswaan terlebih dahulu.',
            'waka_kesiswaan_id.exists' => 'Waka Kesiswaan yang dipilih tidak ditemukan.',
        ]);

        $ttdWaka = preg_match('/^data:image\/png;base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (! $ttdWaka) {
            return back()->withErrors(['ttd_waka' => 'Tanda tangan Waka Kesiswaan wajib digambar terlebih dahulu.']);
        }

        // Auto-detect saat login: TTD milik Waka Kesiswaan yang sedang login.
        $loggedInWaka = Auth::user();
        if ($loggedInWaka instanceof User && $loggedInWaka->isWakaKesiswaan()) {
            $wakaId = (int) $loggedInWaka->id;
        } else {
            $wakaId = (int) $validated['waka_kesiswaan_id'];
        }

        // Pengaman: pastikan user yang dipilih benar-benar Waka Kesiswaan.
        $wakaUser = User::find($wakaId);
        abort_unless($wakaUser instanceof User && $wakaUser->isWakaKesiswaan(), 422, 'Waka Kesiswaan yang dipilih tidak valid.');

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'waka_kesiswaan_id' => $wakaId,
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'approved_at' => now(),
            // Rekam siapa Waka Kesiswaan yang menandatangani surat ini,
            // agar template surat menampilkan nama & NIP penandatangan.
            'approved_by' => $wakaId,
        ]);

        return redirect()->route('dispen.approval.show', $token)
            ->with('success', 'Surat dispensasi berhasil ditandatangani Waka Kesiswaan.');
    }

    /**
     * Daftar dispensasi yang menunggu persetujuan Waka Kurikulum.
     */
    public function indexApproval()
    {
        $this->authorizeKurikulum();

        $daftar = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->where('status', DispensasiSiswa::STATUS_PENDING_WAKA)
            ->orderByDesc('tanggal')
            ->get();

        return view('kurikulum.dispensasi.approval', compact('daftar'));
    }

    /**
     * Waka Kurikulum menandatangani digital pengajuan dispensasi.
     */
    public function storeApproval(Request $request, $id)
    {
        $this->authorizeKurikulum();

        $dispensasi = DispensasiSiswa::findOrFail($id);

        // Guard: surat data testing hanya dapat disetujui oleh IT/QA.
        $this->authorizeTestingMutation($dispensasi);

        abort_unless($dispensasi->status === DispensasiSiswa::STATUS_PENDING_WAKA, 422, 'Hanya dispensasi dengan status Pending Waka yang dapat disetujui pada tahap ini.');

        $validated = $request->validate([
            'ttd_waka' => 'required|string|max:150000',
        ], [
            'ttd_waka.required' => 'Tanda tangan Waka Kurikulum wajib diisi.',
            'ttd_waka.max' => 'Ukuran tanda tangan Waka Kurikulum terlalu besar.',
        ]);

        $ttdWaka = preg_match('/^data:image\/png;base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (! $ttdWaka) {
            return back()->withErrors(['ttd_waka' => 'Tanda tangan Waka Kurikulum wajib digambar terlebih dahulu.']);
        }

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'status' => DispensasiSiswa::STATUS_FINAL,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return redirect()->route('kurikulum.dispensasi.approval.index')
            ->with('success', 'Persetujuan dispensasi berhasil disimpan dengan tanda tangan Waka Kurikulum.');
    }
}
