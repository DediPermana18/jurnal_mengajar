<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Guru\Concerns\ResolvesTargetGuru;
use App\Models\AbsensiJurnal;
use App\Models\CatatanSiswaBermasalah;
use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WaliKelasController extends Controller
{
    use ResolvesTargetGuru;

    /**
     * Dashboard Wali Kelas: ringkasan dispensasi siswa hari ini.
     */
    public function dashboard()
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $isEmptyTarget = $this->isEmptyTargetContext();

        if ($isEmptyTarget) {
            // Layout asli tetap dirender: seluruh card widget menampilkan 0 dan
            // tabel/list kosong memunculkan empty state bawaannya. kelasSaya diberi
            // placeholder agar cabang "Belum Ada Kelas Bimbingan" tidak menggantikan widget.
            return view('walikelas.dashboard', [
                'kelasSaya' => collect([new Kelas]),
                'dispensasiHariIni' => collect(),
                'jumlahDispen' => 0,
                'jumlahDisetujui' => 0,
                'today' => Carbon::today()->toDateString(),
                'totalSiswa' => 0,
                'namaKelasSaya' => '-',
                'namaKelasSingkat' => '-',
                'terlambatHariIni' => 0,
                'perluPerhatian' => 0,
            ]);
        }

        $guruId = $this->effectiveGuruId();
        $kelasSaya = Kelas::where('id_wali_kelas', $guruId)->get();
        if ($user->isPetugasIt() && $kelasSaya->isEmpty()) {
            $kelasSaya = Kelas::all();
        }
        $kelasIds = $kelasSaya->pluck('id');
        $today = Carbon::today()->toDateString();

        $dispensasiHariIni = DispensasiSiswa::with(['siswa.kelas', 'jadwal.mapel', 'guruPiket'])
            ->whereDate('tanggal', $today)
            ->when($kelasIds->isNotEmpty(), fn ($q) => $q->whereHas('siswa', fn ($sq) => $sq->whereIn('id_kelas', $kelasIds)))
            ->orderBy('jam_ke')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy(fn ($d) => $d->siswa?->kelas?->nama ?? 'Tanpa Kelas');

        $jumlahDispen = $dispensasiHariIni->flatten(1)->count();
        $jumlahDisetujui = $dispensasiHariIni->flatten(1)
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->count();

        $totalSiswa = $kelasIds->isEmpty()
            ? 0
            : Siswa::whereIn('id_kelas', $kelasIds)->count();

        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');
        $namaKelasSingkat = $kelasSaya->pluck('nama')->implode(', ');

        $terlambatHariIni = $kelasIds->isEmpty()
            ? 0
            : CatatanTerlambat::whereDate('tanggal', $today)
                ->whereHas('siswa', fn ($sq) => $sq->whereIn('id_kelas', $kelasIds))
                ->count();

        $perluPerhatian = $kelasIds->isEmpty()
            ? 0
            : Siswa::whereIn('id_kelas', $kelasIds)
                ->withCount(['catatanTerlambat', 'catatanBermasalah'])
                ->get()
                ->filter(fn ($s) => $s->catatan_terlambat_count > 3 || $s->catatan_bermasalah_count > 0)
                ->count();

        return view('walikelas.dashboard', compact(
            'kelasSaya',
            'dispensasiHariIni',
            'jumlahDispen',
            'jumlahDisetujui',
            'today',
            'totalSiswa',
            'namaKelasSaya',
            'namaKelasSingkat',
            'terlambatHariIni',
            'perluPerhatian'
        ));
    }

    /**
     * Rekap Absen Siswa Wali Kelas
     */
    public function rekapAbsen()
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $isEmptyTarget = $this->isEmptyTargetContext();

        if ($isEmptyTarget) {
            return view('walikelas.rekap_absen', [
                'rekapAbsen' => collect(),
                'namaKelasSaya' => '-',
            ]);
        }

        $guruId = $this->effectiveGuruId();
        $kelasSaya = Kelas::where('id_wali_kelas', $guruId)->get();
        if ($user->isPetugasIt() && $kelasSaya->isEmpty()) {
            $kelasSaya = Kelas::all();
        }
        $kelasIds = $kelasSaya->pluck('id');
        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');

        $daftarSiswa = Siswa::with('kelas')
            ->whereIn('id_kelas', $kelasIds)
            ->orderBy('nama')
            ->get();

        $rekapAbsen = [];

        foreach ($daftarSiswa as $siswa) {
            // Status presensi akumulatif dari seluruh jurnal guru mapel untuk siswa ini.
            $statusCounts = AbsensiJurnal::where('id_siswa', $siswa->id)
                ->selectRaw('status, COUNT(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status');

            $hadir = (int) $statusCounts->get('Hadir', 0);
            $izin = (int) $statusCounts->get('Izin', 0);
            $sakit = (int) $statusCounts->get('Sakit', 0);
            $alpha = (int) $statusCounts->get('Alpa', 0) + (int) $statusCounts->get('Alpha', 0);

            $total = $hadir + $izin + $sakit + $alpha;
            $persen = $total > 0 ? round(($hadir / $total) * 100, 1) : 100.0;

            $rekapAbsen[] = [
                'siswa' => $siswa,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpha' => $alpha,
                'total' => $total,
                'persentase' => $persen,
                'nama_kelas' => $siswa->kelas?->nama ?? '-',
            ];
        }

        $rekapAbsen = collect($rekapAbsen);

        return view('walikelas.rekap_absen', compact('rekapAbsen', 'namaKelasSaya'));
    }

    /**
     * Riwayat Jurnal Kelas
     */
    public function riwayatJurnal()
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $isEmptyTarget = $this->isEmptyTargetContext();

        if ($isEmptyTarget) {
            return view('walikelas.riwayat_jurnal', [
                'daftarJurnal' => collect(),
                'namaKelasSaya' => '-',
            ]);
        }

        $guruId = $this->effectiveGuruId();
        $kelasSaya = Kelas::where('id_wali_kelas', $guruId)->get();
        if ($user->isPetugasIt() && $kelasSaya->isEmpty()) {
            $kelasSaya = Kelas::all();
        }
        $kelasIds = $kelasSaya->pluck('id');
        $namaKelasSaya = $kelasSaya->pluck('nama_lengkap')->implode(', ');

        // Jurnal mengajar yang dilaksanakan HANYA di kelas bimbingan wali kelas ini.
        $jurnal = Jurnal::with([
            'jadwalPelajaran.mapel',
            'jadwalPelajaran.jamPelajaran',
            'jadwalPelajaran.kelas',
            'guru',
            'guruPengganti',
            'absensiJurnal',
        ])
            ->whereHas('jadwalPelajaran', fn ($q) => $q->whereIn('id_kelas', $kelasIds))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Grouping sesi mengajar: jurnal multi-jam (1 sesi = 1 group_id) menghasilkan 1 baris
        // per jam pelajaran. Tanggal + guru pengajar + mapel + materi yang sama digabung
        // menjadi 1 entri ringkas agar jam beruntun (Jam 1 - 3) tidak terduplikasi.
        $daftarJurnal = $jurnal
            ->groupBy(function (Jurnal $j) {
                $guruPengajarId = $j->guruPengganti?->id ?? $j->guru?->id ?? 'tanpa-guru';

                return implode('|', [
                    $j->tanggal ? $j->tanggal->toDateString() : 'tanpa-tanggal',
                    $guruPengajarId,
                    $j->jadwalPelajaran?->mapel?->id ?? 'tanpa-mapel',
                    trim((string) $j->materi),
                ]);
            })
            ->map(function ($kelompok) {
                $utama = $kelompok->first();

                // Jam pelajaran sesi: ambil semua jam_ke lalu gabungkan rentangnya.
                $jamKeList = $kelompok
                    ->pluck('jadwalPelajaran.jamPelajaran.jam_ke')
                    ->map(fn ($jam) => (int) $jam)
                    ->filter()
                    ->sort()
                    ->values();
                $jamLabel = $this->formatRentangJam($jamKeList);

                // Absensi digabung per siswa UNIK (prefer status non-Hadir antar-jam)
                // agar jumlah HADIR tidak terduplikasi / tidak salah hitung.
                // Relasi presensi di model ini bernama 'absensiJurnal' (setara 'presensiSiswa'),
                // di-load eager via with() sehingga selalu berupa Collection (bukan null).
                $absensiUnik = $this->gabungkanAbsensiSesi($kelompok);

                // Badge kehadiran: jumlah siswa yang BENAR-BENAR HADIR / total siswa tercatat.
                // Siswa berstatus Izin/Sakit/Alpa/Dispen/Terlambat TIDAK dihitung sebagai hadir.
                // Fallback aman: bila hasil hitungan null/kosong, jatuh ke 0 agar badge
                // tidak pernah kehilangan elemen/angka-nya.
                $jumlahHadir = $absensiUnik ? $absensiUnik->where('status', 'Hadir')->count() : 0;
                $totalSiswa = $absensiUnik ? $absensiUnik->count() : 0;

                $guruPengajar = $utama->guruPengganti ?: $utama->guru;

                return [
                    'jurnal' => $utama,
                    'tanggal' => $utama->tanggal,
                    'jam_label' => $jamLabel,
                    'mapel' => $utama->jadwalPelajaran?->mapel?->nama_mapel ?? '-',
                    'guru_pengajar' => $guruPengajar?->nama ?? '-',
                    'materi' => $utama->materi ?: '-',
                    'hadir' => $jumlahHadir,
                    'total_siswa' => $totalSiswa,
                    'ratio_label' => $totalSiswa > 0 ? "{$jumlahHadir}/{$totalSiswa} Siswa" : '0/0 Siswa',
                ];
            })
            ->values();

        return view('walikelas.riwayat_jurnal', compact('daftarJurnal', 'namaKelasSaya'));
    }

    /**
     * Tampilkan detail jurnal mengajar (layout bersama 'guru.jurnal.show')
     * untuk Wali Kelas — view yang sama dipakai di /guru/jurnal/{id}.
     *
     * Menampilkan seluruh bagian lengkap: badge 'Read-Only', informasi jurnal
     * utama (kelas, mapel, status kehadiran guru, guru pengganti, materi),
     * catatan kejadian penting & foto kegiatan KBM, serta rekap presensi siswa
     * (No, NIS, Nama, Status, Keterangan / Foto Surat).
     *
     * Keamanan: hanya jurnal yang mengajar di kelas bimbingan wali kelas yang
     * sedang login (atau target impersonasi Petugas IT) yang diizinkan dilihat.
     * Jurnal tidak menyimpan id_kelas langsung — kelas didapat melalui relasi
     * jadwalPelajaran (jadwal_pelajaran.id_kelas).
     */
    public function showJurnal(Request $request, Jurnal $jurnal)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');
        abort_if($this->isEmptyTargetContext(), 403, 'Pilih target Wali Kelas terlebih dahulu.');

        $guruId = $this->effectiveGuruId();
        $kelasSaya = Kelas::where('id_wali_kelas', $guruId)->get();
        if ($user->isPetugasIt() && $kelasSaya->isEmpty()) {
            $kelasSaya = Kelas::all();
        }
        $kelasIds = $kelasSaya->pluck('id');

        $jurnal->load([
            'jadwalPelajaran.jamPelajaran',
            'jadwalPelajaran.kelas',
            'jadwalPelajaran.mapel',
            'guru',
            'guruPengganti',
            'absensiJurnal.siswa',
        ]);

        // ==== VALIDASI KEPEMILIKAN KELAS BIMBINGAN ====
        // jurnal->kelas_id tidak disimpan langsung; kelas didapat lewat relasi
        // jadwalPelajaran->id_kelas dan harus milik wali kelas yang login.
        abort_unless(
            $jurnal->jadwalPelajaran !== null && $kelasIds->contains($jurnal->jadwalPelajaran->id_kelas),
            403,
            'Anda hanya dapat melihat jurnal dari kelas bimbingan Anda.'
        );

        // Seluruh record jurnal dalam SATU sesi mengajar (jurnal multi-jam di-group),
        // agar detailnya selaras dengan baris pada halaman riwayat.
        $kelompok = $this->kelompokSesiJurnal($jurnal);
        $utama = $kelompok->first(fn (Jurnal $j) => $j->id === $jurnal->id) ?? $jurnal;

        // Konteks sesi mengajar + tab Jam Pelajaran — pola sama dengan guru.jurnal.show.
        $jpOptions = $kelompok->map(function (Jurnal $item) {
            $jam = $item->jadwalPelajaran?->jamPelajaran;

            return [
                'jam_ke' => $jam?->jam_ke,
                'waktu' => $jam ? $this->formatWaktuSesi(collect([$item])) : '-',
                'jurnal_id' => $item->id,
            ];
        })->filter(fn ($option) => $option['jam_ke'] !== null)->values()->all();

        $selectedJamKe = $request->filled('jp') ? (int) $request->input('jp') : null;
        $jurnalTampil = $selectedJamKe === null
            ? $utama
            : ($kelompok->first(fn (Jurnal $item) => (int) $item->jadwalPelajaran?->jamPelajaran?->jam_ke === $selectedJamKe) ?? $utama);

        $absensiMap = $selectedJamKe === null
            ? $this->gabungkanAbsensiSesi($kelompok)
            : $jurnalTampil->absensiJurnal->keyBy('id_siswa');

        $waktu = $selectedJamKe === null && $kelompok->count() > 1
            ? $this->formatWaktuSesi($kelompok)
            : $this->formatWaktuSesi(collect([$jurnalTampil]));

        $jadwal = $jurnalTampil->jadwalPelajaran;
        $jurnal = $jurnalTampil;

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        $today = $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : Carbon::today()->toDateString();

        // Navigasi kembali ke portal Wali Kelas (view dipakai bersama dengan guru).
        $jurnalShowUrl = route('walikelas.riwayat-jurnal.show', $jurnal->id);
        $backUrl = route('walikelas.riwayat-jurnal');
        $backLabel = 'Kembali ke Riwayat Jurnal';

        return view('guru.jurnal.show', compact(
            'jurnal',
            'jadwal',
            'siswas',
            'today',
            'waktu',
            'absensiMap',
            'jpOptions',
            'selectedJamKe',
            'jurnalShowUrl',
            'backUrl',
            'backLabel'
        ));
    }

    /**
     * Kunci "sesi mengajar" untuk pengelompokan: tanggal + guru pengajar +
     * mata pelajaran + materi. Dua jurnal dengan kunci sama dianggap satu sesi
     * (mis. multi-jam beruntun) — selaras dengan pengelompokan di daftar riwayat.
     */
    protected function sesiKey(Jurnal $jurnal): string
    {
        $guruPengajarId = $jurnal->guruPengganti?->id ?? $jurnal->guru?->id ?? 'tanpa-guru';

        return implode('|', [
            $jurnal->tanggal ? $jurnal->tanggal->toDateString() : 'tanpa-tanggal',
            $guruPengajarId,
            $jurnal->jadwalPelajaran?->mapel?->id ?? 'tanpa-mapel',
            trim((string) $jurnal->materi),
        ]);
    }

    /**
     * Kumpulan record jurnal dari sesi mengajar yang sama dengan $jurnal,
     * dibatasi ke kelas & tanggal yang sama lalu difilter via sesiKey().
     */
    protected function kelompokSesiJurnal(Jurnal $jurnal): Collection
    {
        $kelasId = $jurnal->jadwalPelajaran?->id_kelas;

        return Jurnal::with([
            'jadwalPelajaran.mapel',
            'jadwalPelajaran.jamPelajaran',
            'jadwalPelajaran.kelas',
            'guru',
            'guruPengganti',
            'absensiJurnal.siswa',
        ])
            ->when($kelasId, fn ($q) => $q->whereHas('jadwalPelajaran', fn ($sq) => $sq->where('id_kelas', $kelasId)))
            ->when($jurnal->tanggal, fn ($q) => $q->whereDate('tanggal', $jurnal->tanggal->toDateString()))
            ->get()
            ->filter(fn (Jurnal $j) => $this->sesiKey($j) === $this->sesiKey($jurnal))
            ->sortBy(fn (Jurnal $j) => (int) ($j->jadwalPelajaran?->jamPelajaran?->jam_ke ?? 999))
            ->values();
    }

    /**
     * Gabungkan absensi beberapa record jurnal (multi-jam) per siswa unik.
     * Preferensi status: non-Hadir lebih bermakna (Sakit/Izin/Alpa dst.);
     * keterangan antar-jam digabung.
     */
    protected function gabungkanAbsensiSesi(Collection $kelompok): Collection
    {
        return $kelompok
            ->flatMap(fn (Jurnal $j) => $j->absensiJurnal)
            ->groupBy('id_siswa')
            ->map(function ($items) {
                $utama = $items->first(fn ($item) => (string) $item->status !== 'Hadir') ?? $items->first();
                $keterangan = $items->pluck('keterangan')->filter()->unique()->implode(' | ');
                if ($utama && $keterangan !== '') {
                    $utama->keterangan = $keterangan;
                }

                return $utama;
            })
            ->keyBy('id_siswa');
    }

    /**
     * Rentang waktu sesi mengajar: jam mulai (JP pertama) — jam selesai (JP terakhir).
     */
    protected function formatWaktuSesi(Collection $kelompok): string
    {
        $mulai = $kelompok->first()?->jadwalPelajaran?->jamPelajaran?->jam_mulai;
        $selesai = $kelompok->last()?->jadwalPelajaran?->jamPelajaran?->jam_selesai;

        if (! $mulai || ! $selesai) {
            return '-';
        }

        return Carbon::parse($mulai)->format('H.i').' - '.Carbon::parse($selesai)->format('H.i');
    }

    /**
     * Format daftar jam ke menjadi label rentang, misal [1, 2, 3] => "Jam 1 - 3".
     * Jam yang tidak berurutan dipisahkan koma, misal [1, 3] => "Jam 1, Jam 3".
     */
    protected function formatRentangJam(Collection $jamKeList): string
    {
        $jamKeList = $jamKeList->filter()->unique()->sort()->values();

        if ($jamKeList->isEmpty()) {
            return 'Jam -';
        }

        if ($jamKeList->count() === 1) {
            return 'Jam '.$jamKeList->first();
        }

        $bagian = [];
        $mulai = $jamKeList->first();
        $sebelum = $mulai;

        foreach ($jamKeList->slice(1) as $jam) {
            if ((int) $jam === (int) $sebelum + 1) {
                $sebelum = $jam;

                continue;
            }

            $bagian[] = $mulai === $sebelum ? (string) $mulai : "{$mulai} - {$sebelum}";
            $mulai = $jam;
            $sebelum = $jam;
        }

        $bagian[] = $mulai === $sebelum ? (string) $mulai : "{$mulai} - {$sebelum}";

        return 'Jam '.collect($bagian)->implode(', ');
    }

    /**
     * Catatan Siswa Bermasalah — data REALTIME dari Database:
     * - Siswa pada kelas wali kelas ini (auth()->user()->kelasWali()).
     * - Rekap keterlambatan dari CatatanTerlambat (input Satpam) yang penerimanya
     *   mencakup Wali Kelas ini (via penerima_catatan_terlambat, peran guru_piket/wali_kelas).
     * - Rekap Alpha (Alpa) dari AbsensiJurnal guru mapel.
     * - Rekap Dispensasi dari DispensasiSiswa.
     * + Catatan tindak lanjut (panggil ortu) yang tersimpan di DB.
     */
    public function siswaBermasalah()
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        $isEmptyTarget = $this->isEmptyTargetContext();

        if ($isEmptyTarget) {
            return view('walikelas.siswa_bermasalah', [
                'kelasWali' => collect(),
                'rekap' => [],
                'statistikTindakLanjut' => collect(),
            ]);
        }

        $guruId = $this->effectiveGuruId();
        $kelasWali = Kelas::with('siswa')->where('id_wali_kelas', $guruId)->get();
        if ($user->isPetugasIt() && $kelasWali->isEmpty()) {
            $kelasWali = Kelas::with('siswa')->get();
        }
        $kelasIds = $kelasWali->pluck('id');

        $daftarSiswa = Siswa::with('kelas')
            ->whereIn('id_kelas', $kelasIds)
            ->orderBy('nama')
            ->get();

        $statistikTindakLanjut = CatatanSiswaBermasalah::where('id_wali_kelas', $guruId)
            ->get(['id_siswa', 'jenis_tindakan', 'catatan', 'status', 'updated_at'])
            ->keyBy('id_siswa');

        // --- PETA DATA PER SISWA ---
        $rekap = [];

        foreach ($daftarSiswa as $siswa) {
            $riwayatTerlambat = CatatanTerlambat::with('satpam')
                ->where('id_siswa', $siswa->id)
                ->whereHas('penerima', fn ($q) => $q->where('user_id', $guruId))
                ->orderBy('tanggal', 'desc')
                ->orderBy('jam_masuk', 'desc')
                ->get();

            $totalTerlambat = $riwayatTerlambat->count();

            // Alpha: status 'Alpa'/'Alpha' dari presensi jurnal guru mapel.
            $totalAlpha = AbsensiJurnal::where('id_siswa', $siswa->id)
                ->whereIn('status', ['Alpa', 'Alpha'])
                ->count();

            $dispensasi = DispensasiSiswa::where('id_siswa', $siswa->id)
                ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
                ->orderBy('tanggal', 'desc')
                ->get();

            $rekap[] = [
                'siswa' => $siswa,
                'riwayat_terlambat' => $riwayatTerlambat,
                'total_terlambat' => $totalTerlambat,
                'total_alpha' => $totalAlpha,
                'total_dispen' => $dispensasi->count(),
                'dispensasi' => $dispensasi,
                'tindak_lanjut' => $statistikTindakLanjut->get($siswa->id),
            ];
        }

        return view('walikelas.siswa_bermasalah', compact('kelasWali', 'rekap', 'statistikTindakLanjut'));
    }

    /**
     * Simpan tindak lanjut (Panggil Ortu / Tambah Catatan) untuk siswa.
     */
    public function siswaBermasalahStore(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isPetugasIt() || $user->isWaliKelas()), 403, 'Akses ditolak. Halaman ini khusus untuk Wali Kelas.');

        abort_if($this->isEmptyTargetContext(), 403, 'Pilih target Wali Kelas terlebih dahulu.');

        $validated = $request->validate([
            'id_siswa' => 'required|exists:siswa,id',
            'jenis_tindakan' => 'required|in:panggil_ortu,catatan',
            'catatan' => 'nullable|string|max:1000',
            'status' => 'required|in:belum,dipanggil,selesai',
        ], [
            'id_siswa.required' => 'Siswa wajib dipilih.',
            'jenis_tindakan.required' => 'Jenis tindakan wajib dipilih.',
            'catatan.max' => 'Catatan maksimal :max karakter.',
            'status.required' => 'Status tindak lanjut wajib dipilih.',
        ]);

        $kelasIds = $user->kelasWali()->pluck('id');
        if ($user->isPetugasIt() && $kelasIds->isEmpty()) {
            $kelasIds = Kelas::pluck('id');
        }
        $siswa = Siswa::where('id', $validated['id_siswa'])->whereIn('id_kelas', $kelasIds)->first();

        abort_unless($siswa, 403, 'Siswa bukan bagian dari kelas wali kelas Anda.');

        // Guard: tindak lanjut yang sudah ber-flag testing hanya dapat diubah oleh IT/QA.
        $existing = CatatanSiswaBermasalah::where('id_siswa', $siswa->id)
            ->where('id_wali_kelas', $user->id)
            ->first();
        $this->authorizeTestingMutation($existing);

        CatatanSiswaBermasalah::updateOrCreate(
            ['id_siswa' => $siswa->id, 'id_wali_kelas' => $user->id],
            [
                'jenis_tindakan' => $validated['jenis_tindakan'],
                'catatan' => $validated['catatan'] ?? null,
                'status' => $validated['status'],
            ]
        );

        return back()->with('success', 'Tindak lanjut untuk "'.$siswa->nama.'" berhasil disimpan.');
    }
}
