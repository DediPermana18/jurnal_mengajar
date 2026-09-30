<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PengaturanJadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WakaSdmController extends Controller
{
    /**
     * Authorize access for Waka SDM / Kepegawaian, Super Admin, and Petugas IT
     * in preview mode.
     *
     * Gate ini SELARAS dengan $isWakaSdmRole pada sidebar (layouts/app.blade.php):
     * siapapun yang menu "Dashboard SDM / Portal Waka SDM" tampil untuknya pasti
     * diizinkan di sini, dan sebaliknya (tidak ada menu tanpa akses, tidak ada
     * akses tanpa menu). Petugas TU / user operasional lain tetap ditolak.
     */
    protected function authorizeWakaSdm(): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        // Preview role Petugas IT / QA (Switch View As)
        if ($user->hasPreviewRole() && $user->previewRole() === 'waka_sdm') {
            return;
        }

        // Petugas IT / QA Tester langsung: peninjau
        if ($user->isPetugasIt()) {
            return;
        }

        // Waka SDM / Kepegawaian (User::isWakaSdm — mencakup role literal
        // 'waka_sdm'/'admin_sdm'/'sdm' dan sub_role 'waka_sdm'/'sdm'),
        // Admin Utama legacy (role 'admin' + sub_role null), serta
        // Super Admin — dikenali DARI KEDUA bentuk (role 'super_admin' ATAU
        // sub_role 'super_admin') via User::isSuperAdmin(), selaras dengan
        // Gate::before global di AppServiceProvider. Role super legacy
        // 'epic_admin'/'absolute_admin' juga tetap diizinkan.
        $allowed = $user->isWakaSdm()
            || ($user->role === 'admin' && $user->sub_role === null)
            || $user->isSuperAdmin()
            || in_array($user->role, ['epic_admin', 'absolute_admin'], true);

        abort_unless($allowed, 403, 'Akses ditolak. Halaman ini khusus untuk Waka SDM / Kepegawaian.');
    }

    /**
     * Map English day name to Indonesian
     */
    protected function getHariIndonesia(Carbon $date): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[$date->format('l')] ?? 'Senin';
    }

    /**
     * 1. WAKA SDM DASHBOARD
     */
    public function dashboard(Request $request)
    {
        $this->authorizeWakaSdm();

        $now = Carbon::now();
        $todayStr = $now->toDateString();
        $hariIniStr = $this->getHariIndonesia($now);

        $tahunAktif = TahunAjaran::aktif() ?? TahunAjaran::first();

        // 1. Total Guru Aktif di database
        $totalGuruAktif = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->count();

        // 2. Total Guru Terjadwal Hari Ini
        $jadwalHariIni = JadwalPelajaran::with(['guru', 'kelas', 'mapel', 'jamPelajaran'])
            ->where('hari', $hariIniStr)
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        $idGuruTerjadwal = $jadwalHariIni->pluck('id_guru')->filter()->unique();
        $totalGuruTerjadwalHariIni = $idGuruTerjadwal->count();

        // 3. Izin Guru Hari Ini
        $daftarIzinHariIni = IzinGuru::with('user')
            ->whereDate('tanggal', $todayStr)
            ->get();

        $guruIzinHariIniIds = $daftarIzinHariIni->where('status', '!=', IzinGuru::STATUS_DITOLAK)
            ->pluck('user_id')
            ->unique();
        $totalGuruIzinHariIni = $guruIzinHariIniIds->count();

        // 4. Jurnal yang sudah diisi hari ini
        $idJadwalHariIni = $jadwalHariIni->pluck('id');
        $jurnalHariIni = Jurnal::with(['guru', 'guruPengganti'])
            ->whereDate('tanggal', $todayStr)
            ->whereIn('id_jadwal', $idJadwalHariIni)
            ->get()
            ->keyBy('id_jadwal');

        // Guru Hadir Hari Ini: Guru terjadwal yang tidak izin atau yang sudah mengisi jurnal hadir
        $guruHadirIds = $idGuruTerjadwal->diff($guruIzinHariIniIds);
        $totalGuruHadirHariIni = $guruHadirIds->count();

        // 5. Total Sesi & Total Kelas Kosong (Belum Diisi Jurnal / Guru Belum Hadir)
        $totalSesiHariIni = $jadwalHariIni->count();
        $sesiTerisiHariIni = 0;
        $sesiKosongHariIni = 0;

        $kelasStatus = [];
        foreach ($jadwalHariIni as $jadwal) {
            $jurnal = $jurnalHariIni->get($jadwal->id);
            $isTerisi = $jurnal && ! empty($jurnal->materi);

            if ($isTerisi) {
                $sesiTerisiHariIni++;
            } else {
                $sesiKosongHariIni++;
                $kelasStatus[$jadwal->id_kelas] = true;
            }
        }
        $totalKelasKosong = count($kelasStatus);

        // 6. Persentase Kehadiran Guru Bulan Ini
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $period = CarbonPeriod::create($startOfMonth, $now->copy());

        $totalHadirBulanIni = 0;
        $totalIzinBulanIni = 0;

        foreach ($period as $date) {
            $dayOfWeek = $date->dayOfWeek;
            if ($dayOfWeek === Carbon::SUNDAY || $dayOfWeek === Carbon::SATURDAY) {
                continue;
            }

            $hariName = $this->getHariIndonesia($date);
            $scheduledTeachers = JadwalPelajaran::where('hari', $hariName)
                ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
                ->distinct('id_guru')
                ->count('id_guru');

            if ($scheduledTeachers === 0) {
                $scheduledTeachers = max(1, $totalGuruAktif);
            }

            $leaveCount = IzinGuru::whereDate('tanggal', $date->toDateString())
                ->where('status', '!=', IzinGuru::STATUS_DITOLAK)
                ->distinct('user_id')
                ->count('user_id');

            $presentCount = max(0, $scheduledTeachers - $leaveCount);
            $totalHadirBulanIni += $presentCount;
            $totalIzinBulanIni += $leaveCount;
        }

        $persentaseKehadiranBulanIni = ($totalHadirBulanIni + $totalIzinBulanIni) > 0
            ? round(($totalHadirBulanIni / ($totalHadirBulanIni + $totalIzinBulanIni)) * 100, 1)
            : 100;

        // 7. CARD 1: Guru Tidak Hadir / Izin Hari Ini (Real-time Actionable)
        $guruIzinHariIniList = IzinGuru::with(['user', 'approverPiket'])
            ->whereDate('tanggal', $todayStr)
            ->where('status', '!=', IzinGuru::STATUS_DITOLAK)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($izin) use ($jurnalHariIni) {
                // Cari apakah ada sesi jadwal hari ini yang sudah dicover oleh guru pengganti
                $jurnalCover = $jurnalHariIni->first(function ($j) use ($izin) {
                    return $j->id_guru == $izin->user_id && ! empty($j->id_guru_pengganti);
                });

                $izin->guru_pengganti = $jurnalCover?->guruPengganti ?? $izin->approverPiket;

                return $izin;
            });

        // 8. CARD 2: Pantauan Kelas Kosong (Jam Ini / Hari Ini Belum Diisi)
        $kelasKosongHariIniList = $jadwalHariIni->filter(function ($jadwal) use ($jurnalHariIni) {
            $jurnal = $jurnalHariIni->get($jadwal->id);

            return ! $jurnal || empty($jurnal->materi);
        })->map(function ($jadwal) use ($jurnalHariIni, $daftarIzinHariIni) {
            $jurnal = $jurnalHariIni->get($jadwal->id);
            $izin = $daftarIzinHariIni->firstWhere('user_id', $jadwal->id_guru);

            // `waUrl` sengaja TIDAK dibuat di sini: baris yang dirender ke user
            // adalah hasil GROUPING (lihat groupKelasKosongBerurutan), bukan
            // sesi tunggal. Membentuk URL per sesi berarti membangun N URL lalu
            // membuang N-1-nya.
            return (object) [
                'jadwal' => $jadwal,
                'jurnal' => $jurnal,
                'izin' => $izin,
                'guru' => $jadwal->guru,
                'kelas' => $jadwal->kelas,
                'mapel' => $jadwal->mapel,
                'jam' => $jadwal->jamPelajaran,
            ];
        })->sortBy(function ($item) {
            return $item->jam?->jam_ke ?? 99;
        })->values();

        // 8b. Grouping: satu guru yang mengajar kelas + mapel yang sama pada
        // jam-jam BERUNTUN digabung jadi satu baris (mis. Jam Ke-1..4 ->
        // "Jam Ke-1 - 4"). Sesi yang diselingi sesi milik guru lain tetap
        // dipisah — kalau digabung, label rentangnya mengesankan guru
        // mengajar kelas itu penuh di jam-jam yang sebenarnya milik orang lain.
        $kelasKosongHariIniList = $this->groupKelasKosongByGuruKelasMapel($kelasKosongHariIniList);

        // Card mini: 5 sesi per halaman TANPA scrollbar internal, jadi tinggi card
        // adjusts mengikuti 5 baris itu. Jangan diubah tanpa adjusting card.
        $kelasKosongPerPage = 5;

        // Pencarian card "Pantau Kelas Kosong": query param `q`, dicocokkan ke
        // nama guru / nama kelas / nama mapel. Sumbernya collection in-memory
        // $jadwalHariIni (bukan query builder), jadi filtering dilakukan di PHP
        // SEBELUM paginasi — vital, kalau tidak `total()` dan `forPage()`
        // ikut menghitung data yang tidak lolos filter dan pagination jadi
        // tidak presisi.
        $kelasKosongSearch = trim((string) $request->query('q', ''));

        // Disimpan untuk badge "X dari Y Sesi" di header card. Yang dihitung
        // adalah JUMLAH SESI (JP), bukan jumlah baris — grouping hanya
        // meringkas tampilan, tidak boleh mengikis angka total.
        $kelasKosongTotal = $kelasKosongHariIniList->sum('jumlah_sesi');

        // Pencarian menyaring baris hasil grouping. Field yang dicari
        // (guru / kelas / mapel) identik untuk semua sesi dalam satu grup, jadi
        // urutan "grouping lalu filter" menghasilkan hasil yang sama dengan
        // "filter lalu grouping" — tapi lebih murah karena yang difilter
        // lebih sedikit baris.
        if ($kelasKosongSearch !== '') {
            $kelasKosongHariIniList = $kelasKosongHariIniList
                ->filter(fn ($row) => $this->cocokKataKunciKelasKosong($row, $kelasKosongSearch))
                ->values();
        }

        $kelasKosongSesiTersaring = $kelasKosongHariIniList->sum('jumlah_sesi');
        $kelasKosongJumlahBaris = $kelasKosongHariIniList->count();

        $kelasKosongHariIniList = new LengthAwarePaginator(
            $kelasKosongHariIniList->forPage(LengthAwarePaginator::resolveCurrentPage(), $kelasKosongPerPage)->values(),
            $kelasKosongJumlahBaris,
            $kelasKosongPerPage,
            LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );

        // 9. Recent Izin Guru (10 Terakhir untuk riwayat bawah)
        $recentIzin = IzinGuru::with('user')
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        return view('admin.waka-sdm.dashboard', compact(
            'todayStr',
            'hariIniStr',
            'totalGuruAktif',
            'totalGuruTerjadwalHariIni',
            'totalGuruHadirHariIni',
            'totalGuruIzinHariIni',
            'totalKelasKosong',
            'totalSesiHariIni',
            'sesiTerisiHariIni',
            'sesiKosongHariIni',
            'persentaseKehadiranBulanIni',
            'guruIzinHariIniList',
            'kelasKosongHariIniList',
            'kelasKosongSearch',
            'kelasKosongTotal',
            'kelasKosongSesiTersaring',
            'kelasKosongJumlahBaris',
            'recentIzin'
        ));
    }

    /**
     * Gabungkan seluruh sesi kosong dengan guru, kelas, dan mapel yang sama.
     *
     * @param  Collection<int, object>  $items  Sesi kelas kosong.
     * @return Collection<int, object> Baris hasil grouping.
     */
    private function groupKelasKosongByGuruKelasMapel(Collection $items): Collection
    {
        return $items
            ->groupBy(function ($item) {
                $jadwal = $item->jadwal;

                return implode('-', [
                    $jadwal->id_guru,
                    $jadwal->id_kelas,
                    $jadwal->id_mapel,
                ]);
            })
            ->map(fn (Collection $group) => $this->rakitBarisKelasKosong(
                $group->sortBy(fn ($item) => $item->jam?->jam_ke ?? 99)->values()->all()
            ))
            ->values();
    }

    /**
    * Rakit satu baris tabel dari kumpulan sesi dengan identitas yang sama.
     *
     * @param  array<int, object>  $rows
     */
    private function rakitBarisKelasKosong(array $rows): object
    {
        $awal = $rows[0];
        $akhir = $rows[count($rows) - 1];

        $jamAwal = $awal->jam;
        $jamAkhir = $akhir->jam;

        $jamKeAwal = $jamAwal?->jam_ke;
        $jamKeAkhir = $jamAkhir?->jam_ke;
        $jumlahSesi = count($rows);
        $labelJam = $jumlahSesi > 1
            ? 'Jam Ke-'.($jamKeAwal ?? '-').' - '.($jamKeAkhir ?? '-')
            : 'Jam Ke-'.($jamKeAwal ?? '-');
        $waktuMulai = $jamAwal?->jam_mulai;
        $waktuSelesai = $jamAkhir?->jam_selesai;
        $waktuLabel = $waktuMulai && $waktuSelesai
            ? substr((string) $waktuMulai, 0, 5).' - '.substr((string) $waktuSelesai, 0, 5)
            : '';

        return (object) [
            'id' => $awal->jadwal->id,
            'rows' => $rows,
            'jumlah_sesi' => $jumlahSesi,
            'total_jp' => $jumlahSesi,
            'guru' => $awal->guru,
            'kelas' => $awal->kelas,
            'mapel' => $awal->mapel,
            'mataPelajaran' => $awal->mapel,
            'izin' => $awal->izin,
            'jam_ke_awal' => $jamKeAwal,
            'jam_ke_akhir' => $jamKeAkhir,
            'jam_ke_label' => $labelJam,
            'waktu_mulai' => $waktuMulai ? substr((string) $waktuMulai, 0, 5) : '',
            'waktu_selesai' => $waktuSelesai ? substr((string) $waktuSelesai, 0, 5) : '',
            'waktu_label' => $waktuLabel,
            'waUrl' => $this->buatUrlWhatsappKelasKosong($awal, $labelJam),
        ];
    }

    /**
     * Susun URL WhatsApp pengingat untuk satu baris "kelas kosong".
     *
    * `labelJam` sudah diringkas oleh rakitBarisKelasKosong().
     */
    private function buatUrlWhatsappKelasKosong(object $row, string $labelJam): ?string
    {
        if (empty($row->guru?->no_hp)) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $row->guru->no_hp);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62'.substr($cleanPhone, 1);
        }

        $guruName = $row->guru->nama ?? 'Bapak/Ibu Guru';
        $kelasName = $row->kelas->nama_kelas ?? 'Kelas';
        $mapelName = $row->mapel->nama_mapel ?? 'Mata Pelajaran';
        $msg = "Halo {$guruName}, kami dari Waka SDM mengingatkan untuk pengisian Jurnal KBM pada {$kelasName} - {$mapelName} ({$labelJam}). Terima kasih.";

        return 'https://wa.me/'.$cleanPhone.'?text='.urlencode($msg);
    }

    /**
     * Cocokkan satu item "Kelas Kosong" dengan kata kunci pencarian.
     *
     * Pencarian bersifat case-insensitive substring. Field yang dicocokkan:
     * nama guru, nama kelas, dan nama mata pelajaran.
     *
     * `nama_kelas` juga dicocokkan lewat `nama_kelas_lengkap` ("XI RPL 2")
     * karena itulah nilai yang tampil di tabel — sedangkan `nama_kelas` hanya
     * menyimpan bagian ringkas ("2"). Tanpa ini user tidak bisa mencari teks
     * yang justru dia lihat di layar.
     *
     * @param  object  $item  Baris hasil mapping/grouping di WakaSdmController::dashboard().
     */
    private function cocokKataKunciKelasKosong(object $item, string $kataKunci): bool
    {
        $needle = mb_strtolower($kataKunci);

        $kandidat = [
            $item->guru?->nama,
            $item->kelas?->nama_kelas,
            $item->kelas?->nama_kelas_lengkap,
            $item->mapel?->nama_mapel,
        ];

        foreach ($kandidat as $teks) {
            if ($teks !== null && $teks !== '' && str_contains(mb_strtolower((string) $teks), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 2. REKAP PERIZINAN & CUTI GURU PAGE
     */
    public function rekapIzin(Request $request)
    {
        $this->authorizeWakaSdm();

        $query = IzinGuru::with([
            'user',
            'approverPiket',
            'approverWaka',
            'approverKepsek',
        ]);

        // Filter Tanggal Spesifik
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }

        // Filter Rentang Tanggal / Bulan
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $bulan = (int) $request->input('bulan');
            $tahun = (int) $request->input('tahun');
            $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $end = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
            $query->whereBetween('tanggal', [$start, $end]);
        } elseif ($tanggalMulai && $tanggalSelesai) {
            $query->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai]);
        } elseif ($tanggalMulai) {
            $query->whereDate('tanggal', '>=', $tanggalMulai);
        } elseif ($tanggalSelesai) {
            $query->whereDate('tanggal', '<=', $tanggalSelesai);
        }

        // Filter Nama Guru
        if ($request->filled('id_guru')) {
            $query->where('user_id', $request->input('id_guru'));
        }

        // Filter Kategori Izin
        if ($request->filled('kategori_izin') && $request->input('kategori_izin') !== 'semua') {
            $kategori = $request->input('kategori_izin');
            if ($kategori === 'perdin') {
                $query->whereIn('kategori_izin', ['dinas_luar', 'tugas_luar', 'perdin']);
            } else {
                $query->where('kategori_izin', $kategori);
            }
        }

        // Filter Status Approval
        if ($request->filled('status') && $request->input('status') !== 'semua') {
            $query->where('status', $request->input('status'));
        }

        // Search text
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                })
                    ->orWhere('alasan', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        // Summary counts
        $totalPengajuan = (clone $query)->count();
        $totalDisetujui = (clone $query)->where('status', IzinGuru::STATUS_DISETUJUI)->count();
        $totalPending = (clone $query)->whereIn('status', [
            IzinGuru::STATUS_PENDING_PIKET,
            IzinGuru::STATUS_PENDING_WAKA,
            IzinGuru::STATUS_PENDING_KEPSEK,
        ])->count();
        $totalDitolak = (clone $query)->where('status', IzinGuru::STATUS_DITOLAK)->count();

        // Paginate results
        $daftarIzin = $query->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Cari pengganti piket / guru pengganti untuk tiap izin di tanggal tsb
        $daftarIzin->getCollection()->transform(function ($izin) {
            $tanggal = $izin->tanggal ? $izin->tanggal->toDateString() : null;
            if ($tanggal) {
                $jurnalPengganti = Jurnal::with('guruPengganti')
                    ->whereDate('tanggal', $tanggal)
                    ->where('id_guru', $izin->user_id)
                    ->whereNotNull('id_guru_pengganti')
                    ->first();

                $izin->guru_pengganti_cover = $jurnalPengganti?->guruPengganti?->nama;
            } else {
                $izin->guru_pengganti_cover = null;
            }

            return $izin;
        });

        $guruList = User::where('role', User::ROLE_GURU)->orderBy('nama')->get();

        // Daftar pejabat Waka SDM / Kepegawaian (untuk mode standalone / shared link)
        $daftarWakaSdm = User::where(function ($q) {
            $q->where('role', 'waka_sdm')
                ->orWhere(fn ($q2) => $q2->where('role', 'admin')->where('sub_role', 'waka_sdm'));
        })->orderBy('nama')->get();

        // Flag mode langsung-login: user terautentikasi berperan Waka SDM/Kepegawaian?
        $isWakaSdmAuth = (bool) $this->isCurrentUserWakaSdm();

        return view('admin.waka-sdm.rekap-izin', compact(
            'daftarIzin',
            'guruList',
            'totalPengajuan',
            'totalDisetujui',
            'totalPending',
            'totalDitolak',
            'daftarWakaSdm',
            'isWakaSdmAuth'
        ));
    }

    /**
     * Apakah pengguna yang sedang login teridentifikasi sebagai Waka SDM/Kepegawaian?
     */
    protected function isCurrentUserWakaSdm(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasPreviewRole()) {
            return $user->previewRole() === 'waka_sdm';
        }

        return $user->isWakaSdm();
    }

    /**
     * Helper to compute teacher performance statistics for a given month/year
     */
    protected function hitungPerformaGuruBulanan(int $bulan, int $tahun, ?int $idGuru = null, ?int $perPage = null, ?string $search = null): array
    {
        $startOfMonth = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();

        $tahunAktif = TahunAjaran::aktif() ?? TahunAjaran::first();

        // Hitung frekuensi tiap hari sekolah (Senin - Jumat) dalam bulan tersebut
        $dayOccurrences = [
            'Senin' => 0,
            'Selasa' => 0,
            'Rabu' => 0,
            'Kamis' => 0,
            'Jumat' => 0,
            'Sabtu' => 0,
        ];

        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);
        foreach ($period as $date) {
            $hari = $this->getHariIndonesia($date);
            if (isset($dayOccurrences[$hari])) {
                $dayOccurrences[$hari]++;
            }
        }

        // Query Guru
        $guruQuery = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->with(['mapelDiampu', 'jadwalPelajaran' => function ($q) use ($tahunAktif) {
                if ($tahunAktif) {
                    $q->where('id_tahun_ajaran', $tahunAktif->id);
                }
            }])
            ->orderBy('nama');

        if ($idGuru) {
            $guruQuery->where('id', $idGuru);
        }

        if ($search) {
            $guruQuery->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $allGurus = (clone $guruQuery)->get();
        $gurus = $perPage ? $guruQuery->paginate($perPage)->withQueryString() : $allGurus;

        // Hitung Grand Total dari seluruh guru yang cocok ($allGurus)
        $grandTotalJpWajib = 0;
        $grandTotalJpTerealisasi = 0;
        $grandTotalJpCover = 0;
        $grandTotalJpIzin = 0;
        $grandTotalJpAlpha = 0;
        $totalPersentaseAll = 0;

        foreach ($allGurus as $guru) {
            $jadwals = $guru->jadwalPelajaran;
            $jpWajib = 0;
            $jadwalPerHari = [];
            foreach ($jadwals as $jdw) {
                $jpWajib += ($dayOccurrences[$jdw->hari] ?? 0);
                $jadwalPerHari[$jdw->hari] = ($jadwalPerHari[$jdw->hari] ?? 0) + 1;
            }

            $jurnalList = Jurnal::with('guruPengganti')
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where(function ($q) use ($guru) {
                    $q->where('id_guru', $guru->id)
                        ->orWhereHas('jadwalPelajaran', fn ($j) => $j->where('id_guru', $guru->id));
                })
                ->get();

            $jpTerealisasi = 0;
            $jpCover = 0;
            $jpIzinJurnal = 0;

            foreach ($jurnalList as $jurn) {
                $isOriginalGuru = ($jurn->id_guru == $guru->id);
                $hasCover = ! empty($jurn->id_guru_pengganti) && $jurn->id_guru_pengganti != $guru->id;

                if ($jurn->status_kehadiran === 'Hadir' && ! empty($jurn->materi)) {
                    if ($hasCover && ! $isOriginalGuru) {
                        $jpCover++;
                    } else {
                        $jpTerealisasi++;
                    }
                } elseif (in_array($jurn->status_kehadiran, ['Izin', 'Sakit', 'Disposisi'])) {
                    $jpIzinJurnal++;
                    if ($hasCover) {
                        $jpCover++;
                    }
                }
            }

            $izinList = IzinGuru::where('user_id', $guru->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', IzinGuru::STATUS_DISETUJUI)
                ->get();

            $jpIzinResmi = 0;
            foreach ($izinList as $iz) {
                $hariIz = $this->getHariIndonesia($iz->tanggal);
                $jpIzinResmi += ($jadwalPerHari[$hariIz] ?? 0);
            }

            $jpIzin = max($jpIzinJurnal, $jpIzinResmi);
            $jpTerpenuhi = $jpTerealisasi + $jpCover;
            $jpAlpha = max(0, $jpWajib - ($jpTerpenuhi + $jpIzin));

            $persentase = $jpWajib > 0
                ? min(100, round(($jpTerpenuhi / $jpWajib) * 100, 1))
                : 100;

            $grandTotalJpWajib += $jpWajib;
            $grandTotalJpTerealisasi += $jpTerealisasi;
            $grandTotalJpCover += $jpCover;
            $grandTotalJpIzin += $jpIzin;
            $grandTotalJpAlpha += $jpAlpha;
            $totalPersentaseAll += $persentase;
        }

        $rataRataKedisiplinan = $allGurus->count() > 0
            ? round($totalPersentaseAll / $allGurus->count(), 1)
            : 0;

        // Data rekap untuk baris tabel yang ditampilkan (sesuai paginasi $gurus)
        $dataRekap = [];
        foreach ($gurus as $guru) {
            // 1. Hitung JP Wajib berdasarkan plotting jadwal mingguan * frekuensi hari di bulan tsb
            $jadwals = $guru->jadwalPelajaran;
            $jpWajib = 0;
            $jadwalPerHari = [];

            foreach ($jadwals as $jdw) {
                $jpWajib += ($dayOccurrences[$jdw->hari] ?? 0);
                $jadwalPerHari[$jdw->hari] = ($jadwalPerHari[$jdw->hari] ?? 0) + 1;
            }

            // 2. Query Jurnal yang diisi oleh guru ini atau untuk jadwal guru ini pada bulan tersebut
            $jurnalList = Jurnal::with('guruPengganti')
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where(function ($q) use ($guru) {
                    $q->where('id_guru', $guru->id)
                        ->orWhereHas('jadwalPelajaran', fn ($j) => $j->where('id_guru', $guru->id));
                })
                ->get();

            $jpTerealisasi = 0;
            $jpCover = 0;
            $jpIzinJurnal = 0;

            foreach ($jurnalList as $jurn) {
                $isOriginalGuru = ($jurn->id_guru == $guru->id);
                $hasCover = ! empty($jurn->id_guru_pengganti) && $jurn->id_guru_pengganti != $guru->id;

                if ($jurn->status_kehadiran === 'Hadir' && ! empty($jurn->materi)) {
                    if ($hasCover && ! $isOriginalGuru) {
                        $jpCover++;
                    } else {
                        $jpTerealisasi++;
                    }
                } elseif (in_array($jurn->status_kehadiran, ['Izin', 'Sakit', 'Disposisi'])) {
                    $jpIzinJurnal++;
                    if ($hasCover) {
                        $jpCover++;
                    }
                }
            }

            // 3. Izin resmi dari tabel izin_guru pada bulan tersebut
            $izinList = IzinGuru::where('user_id', $guru->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', IzinGuru::STATUS_DISETUJUI)
                ->get();

            $jpIzinResmi = 0;
            foreach ($izinList as $iz) {
                $hariIz = $this->getHariIndonesia($iz->tanggal);
                $jpIzinResmi += ($jadwalPerHari[$hariIz] ?? 0);
            }

            $jpIzin = max($jpIzinJurnal, $jpIzinResmi);
            $jpTerpenuhi = $jpTerealisasi + $jpCover;
            $jpAlpha = max(0, $jpWajib - ($jpTerpenuhi + $jpIzin));

            // Persentase Kedisiplinan / Realisasi KBM
            $persentase = $jpWajib > 0
                ? min(100, round(($jpTerpenuhi / $jpWajib) * 100, 1))
                : 100;

            // Rating Kinerja
            if ($persentase >= 90) {
                $kategoriKinerja = 'Sangat Baik';
                $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
            } elseif ($persentase >= 75) {
                $kategoriKinerja = 'Baik';
                $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
            } elseif ($persentase >= 60) {
                $kategoriKinerja = 'Cukup';
                $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
            } else {
                $kategoriKinerja = 'Perlu Evaluasi';
                $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            }

            $dataRekap[] = (object) [
                'guru' => $guru,
                'mapel' => $guru->mapelDiampu->unique('id')->pluck('nama_mapel')->implode(', ') ?: '-',
                'jpWajib' => $jpWajib,
                'jpTerealisasi' => $jpTerealisasi,
                'jpCover' => $jpCover,
                'jpIzin' => $jpIzin,
                'jpAlpha' => $jpAlpha,
                'persentase' => $persentase,
                'kategoriKinerja' => $kategoriKinerja,
                'badgeClass' => $badgeClass,
            ];
        }

        return [
            'gurus' => $gurus,
            'dataRekap' => $dataRekap,
            'totalGuru' => $allGurus->count(),
            'grandTotalJpWajib' => $grandTotalJpWajib,
            'grandTotalJpTerealisasi' => $grandTotalJpTerealisasi,
            'grandTotalJpCover' => $grandTotalJpCover,
            'grandTotalJpIzin' => $grandTotalJpIzin,
            'grandTotalJpAlpha' => $grandTotalJpAlpha,
            'rataRataKedisiplinan' => $rataRataKedisiplinan,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'namaBulan' => Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F'),
            'tahunAktif' => $tahunAktif,
        ];
    }

    /**
     * 3. REKAP PRESENSI & JURNAL KBM GURU
     */
    public function rekapPresensiGuru(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;
        $search = $request->input('search');

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru, 10, $search);
        $guruList = User::where('role', User::ROLE_GURU)->orderBy('nama')->get();

        return view('admin.waka-sdm.rekap-presensi-guru', array_merge($stats, [
            'guruList' => $guruList,
            'selectedGuru' => $idGuru,
        ]));
    }

    /**
     * Export Excel (.xls) Laporan Bulanan Kedisiplinan Guru
     */
    public function exportExcelPresensi(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;
        $search = $request->input('search');

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru, null, $search);

        $html = "\xEF\xBB\xBF".view('admin.waka-sdm.excel-presensi-guru', $stats)->render();

        $filename = 'laporan-kedisiplinan-guru-'.str_pad($bulan, 2, '0', STR_PAD_LEFT).'-'.$tahun.'.xls';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Print PDF Laporan Bulanan Kedisiplinan Guru kepada Kepala Sekolah
     */
    public function printPresensi(Request $request)
    {
        $this->authorizeWakaSdm();

        $bulan = (int) $request->input('bulan', Carbon::now()->month);
        $tahun = (int) $request->input('tahun', Carbon::now()->year);
        $idGuru = $request->filled('id_guru') ? (int) $request->input('id_guru') : null;
        $search = $request->input('search');

        $stats = $this->hitungPerformaGuruBulanan($bulan, $tahun, $idGuru, null, $search);

        $kepsek = User::where('role', 'kepala_sekolah')
            ->orWhere('sub_role', 'kepala_sekolah')
            ->first();

        $wakaSdm = Auth::user();

        return view('admin.waka-sdm.print-presensi-guru', array_merge($stats, [
            'kepsek' => $kepsek,
            'wakaSdm' => $wakaSdm,
        ]));
    }

    /**
     * Show Lampiran / Bukti Surat Izin Guru
     */
    public function showLampiran($id)
    {
        $this->authorizeWakaSdm();

        $izin = IzinGuru::findOrFail($id);

        if (! $izin->lampiran) {
            abort(404, 'Lampiran surat tidak ditemukan.');
        }

        if (Storage::disk('public')->exists($izin->lampiran)) {
            return Storage::disk('public')->response($izin->lampiran);
        }

        if (Storage::disk('local')->exists($izin->lampiran)) {
            return Storage::disk('local')->response($izin->lampiran);
        }

        abort(404, 'File lampiran fisik tidak ditemukan di server.');
    }

    /**
     * Menyetujui pengajuan izin guru (Waka SDM approval).
     */
    public function approveIzin($id)
    {
        $this->authorizeWakaSdm();

        $izin = IzinGuru::with('user')->findOrFail($id);

        // Guard: cegah self-approval — Waka tidak boleh menyetujui izinnya sendiri.
        if ($izin->user_id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menyetujui pengajuan izin Anda sendiri!');
        }

        // Guard: izin data testing hanya dapat diproses oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        $level = PengaturanJadwal::izinApprovalLevel();

        if ($level === 3) {
            abort_unless(
                $izin->status === IzinGuru::STATUS_PENDING_WAKA,
                422,
                'Hanya izin yang telah diverifikasi Guru Piket yang dapat diproses Waka SDM.'
            );
        } else {
            abort_unless(
                $izin->status === IzinGuru::STATUS_PENDING_WAKA || $izin->status === IzinGuru::STATUS_PENDING_PIKET,
                422,
                'Hanya izin berstatus Menunggu Approval yang dapat disetujui pada langkah ini.'
            );
        }
        $data = ['catatan_penolakan' => null];

        $data['approved_by_waka'] = $izin->approved_by_waka ?? auth()->id();
        $data['status'] = match ($level) {
            2 => IzinGuru::STATUS_DISETUJUI,
            1 => IzinGuru::STATUS_DISETUJUI,
            default => IzinGuru::STATUS_PENDING_KEPSEK,
        };

        if ($data['status'] === IzinGuru::STATUS_DISETUJUI) {
            $data['approved_at'] = now();
        }

        $izin->update($data);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->back()
            ->with('success', "Izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} berhasil disetujui (Status: '{$izin->fresh()->status_label}').");
    }

    /**
     * APPROVAL TANDA TANGAN WAKA SDM (Dual-Mode).
     *
     * 1. Mode langsung-login (direct_login):
     *    Pengguna terautentikasi berperan Waka SDM -> otomatis memakai auth id,
     *    penandatangan dikunci (dropdown disembunyikan).
     * 2. Mode standalone / shared-link (shared_link):
     *    Dibuka via tautan publik / oleh petugas yang tidak berperan Waka SDM ->
     *    petugas memilih pejabat Waka SDM dari dropdown sebelum menggoreskan tanda tangan.
     *
     * Kolom yang dipakai (skema existing):
     *   - approved_by_waka  = ID Waka SDM terpilih / dari session (waka_sdm_id)
     *   - ttd_waka          = path/gambar tanda tangan digital (waka_signature_path)
     *   - approved_at       = timestamp persetujuan
     * approval_method ditentukan dari konteks request (direct_login | shared_link).
     * Setelah ditandatangani, status lanjut ke Pending Kepsek untuk persetujuan akhir.
     */
    public function approveIzinSignature(Request $request, $id)
    {
        $this->authorizeWakaSdm();

        $izin = IzinGuru::with('user')->findOrFail($id);

        // Guard: cegah self-approval — Waka tidak boleh menyetujui izinnya sendiri.
        if ($izin->user_id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menyetujui pengajuan izin Anda sendiri!');
        }

        // Guard: izin data testing hanya dapat diproses oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        abort_unless(
            in_array($izin->status, [
                IzinGuru::STATUS_PENDING_WAKA,
                IzinGuru::STATUS_PENDING_PIKET,
                IzinGuru::STATUS_PENDING_KEPSEK,
            ], true),
            422,
            'Hanya izin yang sedang dalam proses approval yang dapat ditandatangani.'
        );

        abort_if($izin->ttd_waka !== null, 422, 'Izin ini sudah ditandatangani Waka SDM.');

        // === Tentukan mode ===
        $isWakaSdmAuth = $this->isCurrentUserWakaSdm();
        $approvalMethod = $isWakaSdmAuth ? 'direct_login' : 'shared_link';

        // Penandatangan Waka SDM
        if ($isWakaSdmAuth) {
            $wakaSdmId = Auth::id();
        } else {
            $validated = $request->validate([
                'waka_sdm_id' => 'required|integer|exists:users,id',
                'ttd_waka' => 'required|string|max:150000',
            ], [
                'waka_sdm_id.required' => 'Silakan pilih pejabat Waka SDM / Kepegawaian terlebih dahulu.',
                'ttd_waka.required' => 'Tanda tangan Waka SDM wajib diisi.',
            ]);
            $wakaSdmId = (int) $validated['waka_sdm_id'];
        }

        // Tanda tangan digital (data URL base64 PNG dari Canvas), validasi pola.
        $ttdWaka = $this->takeTtdSignature((string) $request->input('ttd_waka'));
        if (! $ttdWaka) {
            return back()->with('error', 'Tanda tangan Waka SDM wajib diisi (goreskan pada area tanda tangan).');
        }

        $izin->update([
            'approved_by_waka' => $wakaSdmId,
            'ttd_waka' => $ttdWaka,
            'approved_at' => now(),
            'status' => IzinGuru::STATUS_PENDING_KEPSEK,
            'catatan_penolakan' => null,
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->back()
            ->with('success', "Tanda tangan Waka SDM ({$izin->fresh()->approverWaka?->nama}) berhasil dicatat untuk izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')}."
                .' Status dilanjutkan ke Kepala Sekolah. (Metode: '.strtoupper($approvalMethod).')');
    }

    /**
     * Validasi & bersihkan data tanda tangan digital (data URL PNG base64).
     */
    protected function takeTtdSignature(?string $value): ?string
    {
        $value = $value ? trim((string) $value) : null;
        if (! $value || ! preg_match('/^data:image\/png;base64,/i', $value)) {
            return null;
        }

        try {
            $raw = substr($value, strpos($value, ',') + 1);
            $decoded = base64_decode($raw, true);
            if ($decoded === false || strlen($decoded) > (5 * 1024 * 1024)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $value;
    }

    /**
     * Menolak pengajuan izin guru beserta catatan penolakan.
     */
    public function rejectIzin(Request $request, $id)
    {
        $this->authorizeWakaSdm();

        $izin = IzinGuru::with('user')->findOrFail($id);

        // Guard: izin data testing hanya dapat diproses oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        $level = PengaturanJadwal::izinApprovalLevel();
        if ($level === 3 && $izin->status === IzinGuru::STATUS_PENDING_PIKET) {
            abort(422, 'Pengajuan izin masih menunggu verifikasi Guru Piket.');
        }

        abort_if(
            $izin->status === IzinGuru::STATUS_DISETUJUI || $izin->status === IzinGuru::STATUS_DITOLAK,
            422,
            'Izin ini sudah diproses dan tidak dapat ditolak lagi.'
        );

        $validated = $request->validate([
            'catatan_penolakan' => 'required|string|min:3|max:1000',
        ], [
            'catatan_penolakan.required' => 'Catatan penolakan wajib diisi.',
            'catatan_penolakan.min' => 'Catatan penolakan minimal :min karakter.',
            'catatan_penolakan.max' => 'Catatan penolakan maksimal :max karakter.',
        ]);

        $izin->update([
            'status' => IzinGuru::STATUS_DITOLAK,
            'approved_at' => now(),
            'approved_by_waka' => $izin->approved_by_waka ?? auth()->id(),
            'catatan_penolakan' => $validated['catatan_penolakan'],
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->back()
            ->with('success', "Izin {$izin->user->nama} pada {$izin->tanggal->translatedFormat('d F Y')} berhasil ditolak. Catatan penolakan telah disimpan.");
    }

    /**
     * Halaman Pengaturan Alur Approval Izin Guru (level & nomor WA).
     */
    public function settingIzin()
    {
        $this->authorizeWakaSdm();

        $setting = PengaturanJadwal::getSetting();
        $level = PengaturanJadwal::izinApprovalLevel();
        $noWaWaka = PengaturanJadwal::noWaWakaIzin();
        $noWaKepsek = PengaturanJadwal::noWaKepsek();

        return view('admin.waka-sdm.setting-izin', compact('setting', 'level', 'noWaWaka', 'noWaKepsek'));
    }

    /**
     * Simpan Pengaturan Alur Approval Izin Guru.
     */
    public function updateSettingIzin(Request $request)
    {
        $this->authorizeWakaSdm();

        $validated = $request->validate([
            'izin_approval_level' => 'required|integer|in:1,2,3',
            'no_wa_waka' => 'nullable|string|max:20',
            'no_wa_kepsek' => 'nullable|string|max:20',
        ], [
            'izin_approval_level.required' => 'Level approval wajib dipilih.',
            'izin_approval_level.in' => 'Level approval tidak valid.',
            'no_wa_waka.max' => 'Nomor WA Waka SDM maksimal :max karakter.',
            'no_wa_kepsek.max' => 'Nomor WA Kepsek maksimal :max karakter.',
        ]);

        $setting = PengaturanJadwal::getSetting();

        // Guard: setting data testing hanya dapat diubah oleh IT/QA.
        $this->authorizeTestingMutation($setting);

        $setting->update([
            'izin_approval_level' => (int) $validated['izin_approval_level'],
            'no_wa_waka' => $this->normalizePhoneNumber($validated['no_wa_waka'] ?? ''),
            'no_wa_kepsek' => $this->normalizePhoneNumber($validated['no_wa_kepsek'] ?? ''),
        ]);

        return redirect()->route('waka-sdm.izin.setting')
            ->with('success', 'Pengaturan alur approval Izin Guru berhasil disimpan.');
    }

    /**
     * Normalisasi nomor telepon ke format internasional (62...)
     */
    protected function normalizePhoneNumber(?string $value): ?string
    {
        $no = preg_replace('/[^0-9]/', '', trim((string) $value));
        if ($no === '') {
            return null;
        }
        if (str_starts_with($no, '0')) {
            $no = '62'.substr($no, 1);
        }

        return $no;
    }
}
