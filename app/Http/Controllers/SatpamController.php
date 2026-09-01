<?php

namespace App\Http\Controllers;

use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\PenerimaTerlambat;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SatpamController extends Controller
{
    /**
     * Portal Satpam bersifat independen: bebas dari cek jadwal piket guru,
     * Satpam (role admin + sub_role satpam / role lama piket_satpam) selalu
     * dapat mengakses portal ini kapan saja.
     */
    protected function authorizeSatpam(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isSatpam(), 403, 'Akses ditolak. Portal khusus Satpam / Petugas Keamanan.');
    }

    /**
     * Nama hari Indonesia untuk hari ini (Senin..Sabtu), null saat Minggu.
     */
    protected static function namaHariToday(): ?string
    {
        $map = [
            Carbon::MONDAY    => 'Senin',
            Carbon::TUESDAY   => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY  => 'Kamis',
            Carbon::FRIDAY    => 'Jumat',
            Carbon::SATURDAY  => 'Sabtu',
        ];

        return $map[now()->dayOfWeek] ?? null;
    }

    /**
     * Semua User (Guru Piket) yang bertugas hari ini berdasarkan tabel jadwal_piket.
     */
    protected static function guruPiketBertugasHariIni()
    {
        $hari = static::namaHariToday();

        if (!$hari) {
            return collect();
        }

        return User::whereHas('jadwalPiket', fn ($q) => $q->where('hari', $hari))
            ->orderBy('nama')
            ->get();
    }

    /**
     * Jam ke- yang sedang / akan segera berlangsung berdasarkan waktu saat ini.
     * Kategori hari mengikuti jadwal: Jumat vs Senin-Kamis.
     */
    protected function jamKeSekarang(): ?int
    {
        $kategoriHari = now()->isFriday() ? 'Jumat' : 'Senin-Kamis';
        $nowTime      = now()->format('H:i:s');

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
     * Jadwal KBM hari ini per kelas (mapel + guru pengajar + jam ke), untuk
     * dropdown "Mata Pelajaran / Guru Pengajar" yang sedang mengajar.
     */
    protected function jadwalHariIniByKelas(): array
    {
        $hari   = static::namaHariToday();
        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();
        $jamKe  = $this->jamKeSekarang();

        $jadwalHariIni = JadwalPelajaran::with(['jamPelajaran', 'mapel', 'guru'])
            ->when($hari, fn ($q) => $q->where('hari', $hari))
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get()
            ->filter(fn (JadwalPelajaran $j) => $j->jamPelajaran?->jam_ke !== null);

        $map = [];
        foreach ($jadwalHariIni as $j) {
            $map[$j->id_kelas][] = [
                'id_jadwal' => $j->id,
                'jam_ke'    => (int) $j->jamPelajaran->jam_ke,
                'mapel'     => $j->mapel?->nama_mapel ?? '-',
                'guru'      => $j->guru?->nama ?? '-',
                'waktu'     => $j->jamPelajaran->rentang_waktu,
                'aktif'     => $jamKe !== null && (int) $j->jamPelajaran->jam_ke === $jamKe,
            ];
        }

        return $map;
    }

    /**
     * Dashboard Satpam fokus kedisiplinan siswa di gerbang dengan 2 tab:
     * - Tab 1 "Input Siswa Terlambat" (form + rekap, otomatis diteruskan ke
     *   semua Guru Piket hari ini & Wali Kelas siswa).
     * - Tab 2 "Input / Cek Dispensasi" (form dispen + auto-detect mapel/guru
     *   + rekap dispensasi hari ini + verifikasi surat izin).
     */
    public function dashboard(Request $request)
    {
        $this->authorizeSatpam();

        $today = now()->toDateString();
        $tab   = in_array($request->get('tab'), ['terlambat', 'dispensasi'], true)
            ? $request->get('tab')
            : 'terlambat';

        $daftarTerlambat = CatatanTerlambat::with(['siswa.kelas', 'satpam', 'penerima.user'])
            ->whereDate('tanggal', $today)
            ->orderByDesc('jam_masuk')
            ->get();

        $totalTerlambat = $daftarTerlambat->count();

        $totalIzinKeluar = DispensasiSiswa::whereDate('tanggal', $today)
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->whereNotNull('keluar_gerbang_at')
            ->count();

        $totalDispenDisetujui = DispensasiSiswa::whereDate('tanggal', $today)
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->count();

        $daftarIzinKeluar = DispensasiSiswa::with(['siswa.kelas', 'verifier'])
            ->whereDate('tanggal', $today)
            ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
            ->orderByRaw('(keluar_gerbang_at IS NULL) DESC, keluar_gerbang_at DESC, id DESC')
            ->limit(20)
            ->get();

        $kelasList = Kelas::withCount('siswa')->orderBy('tingkat')->orderBy('nama_kelas')->get();
        $siswaList = Siswa::with('kelas')->orderBy('nama')->get();

        $jamKeSekarang    = $this->jamKeSekarang();
        $mapJadwalKelas   = $this->jadwalHariIniByKelas();
        $jenisOptions     = DispensasiSiswa::JENIS_LABELS;
        $guruPiketHariIni = static::guruPiketBertugasHariIni();

        return view('satpam.dashboard', compact(
            'today',
            'tab',
            'totalTerlambat',
            'daftarTerlambat',
            'totalIzinKeluar',
            'totalDispenDisetujui',
            'daftarIzinKeluar',
            'kelasList',
            'siswaList',
            'jamKeSekarang',
            'mapJadwalKelas',
            'jenisOptions',
            'guruPiketHariIni'
        ));
    }

    /**
     * Catat siswa terlambat di gerbang.
     * Record OTOMATIS dihubungkan ke SEMUA Guru Piket yang bertugas hari ini
     * (tabel jadwal_piket) dan ke Wali Kelas siswa (siswa->kelas->id_wali_kelas).
     */
    public function terlambatStore(Request $request)
    {
        $this->authorizeSatpam();

        $data = $request->validate([
            'id_siswa'   => 'required|exists:siswa,id',
            'tanggal'    => 'required|date',
            'jam_masuk'  => 'required|date_format:H:i',
            'keterangan' => 'nullable|string|max:191',
        ]);

        $siswa = Siswa::with('kelas.waliKelas')->findOrFail($data['id_siswa']);

        $catatan = CatatanTerlambat::create([
            'id_siswa'   => $siswa->id,
            'tanggal'    => $data['tanggal'],
            'jam_masuk'  => $data['jam_masuk'],
            'keterangan' => $data['keterangan'] ?? null,
            'id_satpam'  => Auth::id(),
        ]);

        // 1) Hubungkan ke SEMUA Guru Piket yang bertugas hari ini.
        foreach (static::guruPiketBertugasHariIni() as $guru) {
            $catatan->penerima()->updateOrCreate(
                ['user_id' => $guru->id, 'peran' => PenerimaTerlambat::PERAN_GURU_PIKET],
                []
            );
        }

        // 2) Hubungkan ke Wali Kelas siswa.
        if ($waliKelasId = $siswa->kelas?->id_wali_kelas) {
            $catatan->penerima()->updateOrCreate(
                ['user_id' => $waliKelasId, 'peran' => PenerimaTerlambat::PERAN_WALI_KELAS],
                []
            );
        }

        $jumlahPenerima = $catatan->penerima()->count();

        NotificationService::siswaTerlambat($catatan->load('penerima'));

        return back()->with('success', 'Siswa "' . $siswa->nama . '" tercatat terlambat pukul ' . $data['jam_masuk']
            . ' dan diteruskan ke ' . $catatan->jumlah_guru_piket . ' Guru Piket serta Wali Kelas (total ' . $jumlahPenerima . ' penerima).');
    }

    /**
     * Catat DISPENSASI siswa dari gerbang (langsung disetujui oleh Satpam).
     * Menandai absensi siswa sebagai 'Dispen' pada Jurnal Mengajar Guru
     * Mapel yang sedang/layak ditinggalkan hari ini.
     */
    public function dispensasiStore(Request $request)
    {
        $this->authorizeSatpam();

        $validated = $request->validate([
            'tanggal'   => 'required|date',
            'id_siswa'  => 'required|exists:siswa,id',
            'jenis'     => 'required|in:' . implode(',', array_keys(DispensasiSiswa::JENIS_LABELS)),
            'id_jadwal' => 'nullable|exists:jadwal_pelajaran,id',
            'alasan'    => 'required|string|max:500',
        ], [
            'tanggal.required'  => 'Tanggal dispensasi wajib diisi.',
            'id_siswa.required' => 'Nama siswa wajib dipilih.',
            'id_siswa.exists'   => 'Siswa yang dipilih tidak ditemukan.',
            'jenis.required'    => 'Jenis dispensasi wajib dipilih.',
            'jenis.in'          => 'Jenis dispensasi tidak valid.',
            'alasan.required'   => 'Alasan dispensasi wajib diisi.',
            'alasan.max'        => 'Alasan maksimal :max karakter.',
        ]);

        $siswa  = Siswa::with('kelas')->findOrFail($validated['id_siswa']);
        $hari   = static::namaHariToday();
        $jadwal = !empty($validated['id_jadwal'])
            ? JadwalPelajaran::with('jamPelajaran')->find((int) $validated['id_jadwal'])
            : null;

        if ($jadwal && ($jadwal->id_kelas !== $siswa->id_kelas || $jadwal->hari !== $hari)) {
            return back()->withErrors(['id_jadwal' => 'Jadwal yang dipilih tidak cocok dengan kelas/hari siswa.'])
                ->withInput();
        }

        $jamKe  = $jadwal?->jamPelajaran?->jam_ke;
        $idGuru = $jadwal?->id_guru;

        $dispen = DispensasiSiswa::create([
            'id_siswa'       => $siswa->id,
            'id_guru_piket'  => Auth::id(),
            'id_jadwal'      => $jadwal?->id,
            'id_guru'        => $idGuru,
            'tanggal'        => $validated['tanggal'],
            'jenis'          => $validated['jenis'],
            'jam_ke'         => $jamKe !== null ? (string) $jamKe : null,
            'alasan'         => $validated['alasan'],
            'status'         => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at'    => now(),
            'approved_by'    => Auth::id(),
            'approval_token' => (string) Str::uuid(),
        ]);

        $jumlahAbsensi = $dispen->terapkanKeAbsensi();

        NotificationService::siswaDispen($dispen->load('siswa.kelas'));

        return redirect()->route('satpam.dashboard', ['tab' => 'dispensasi'])
            ->with('success', 'Dispensasi "' . $siswa->nama . '" (' . $dispen->jenis_label . ', jam ke-' . ($jamKe ?? '-')
                . ') dicatat & disetujui. ' . $jumlahAbsensi . ' baris absensi jurnal Guru Mapel ditandai Dispen.');
    }

    /**
     * Verifikasi Dispensasi / Izin Keluar di gerbang:
     * input Kode Unik (token surat) atau cari NIS/NISN/nama siswa.
     */
    public function verifikasi(Request $request)
    {
        $this->authorizeSatpam();

        $q = trim((string) $request->get('q', ''));

        $dispen       = null;
        $siswa        = null;
        $daftarDispen = collect();

        if ($q !== '') {
            // 1. Cek langsung via kode unik pada surat dispensasi digital.
            $dispen = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'jadwal.mapel'])
                ->where('approval_token', $q)
                ->first();

            // 2. Jika bukan kode, cari siswa berdasarkan NIS / NISN / nama.
            if (!$dispen) {
                $siswa = Siswa::with('kelas')
                    ->where(function ($query) use ($q) {
                        $query->where('nis', $q)
                            ->orWhere('nisn', $q)
                            ->orWhere('nama', 'like', '%' . $q . '%');
                    })
                    ->first();

                if ($siswa) {
                    $daftarDispen = DispensasiSiswa::with(['guruPiket', 'jadwal.mapel'])
                        ->where('id_siswa', $siswa->id)
                        ->where('status', DispensasiSiswa::STATUS_DISETUJUI)
                        ->orderByDesc('tanggal')
                        ->orderByDesc('id')
                        ->limit(10)
                        ->get();

                    if ($daftarDispen->isNotEmpty()) {
                        $today = now()->toDateString();
                        $dispen = $daftarDispen->first(fn ($d) => $d->tanggal->toDateString() === $today)
                            ?? $daftarDispen->first();
                    }
                }
            }
        }

        return view('satpam.verifikasi', compact('q', 'dispen', 'siswa', 'daftarDispen'));
    }

    /**
     * Izinkan siswa keluar gerbang setelah menunjukkan surat izin digitalnya.
     */
    public function dispenKeluar(Request $request, DispensasiSiswa $dispen)
    {
        $this->authorizeSatpam();

        abort_unless($dispen->isApproved(), 422, 'Dispensasi ini belum disetujui, tidak dapat diizinkan keluar.');

        if ($dispen->isKeluarGerbang()) {
            return redirect()->route('satpam.verifikasi', ['q' => $dispen->approval_token ?? ''])
                ->with('info', 'Siswa "' . $dispen->siswa?->nama . '" sudah diizinkan keluar sebelumnya.');
        }

        $dispen->update([
            'keluar_gerbang_at' => now(),
            'keluar_gerbang_by' => Auth::id(),
        ]);

        return redirect()->route('satpam.verifikasi', ['q' => $dispen->approval_token ?? ''])
            ->with('success', 'Siswa "' . $dispen->siswa?->nama . '" diizinkan keluar gerbang. Selamat jalan!');
    }
}