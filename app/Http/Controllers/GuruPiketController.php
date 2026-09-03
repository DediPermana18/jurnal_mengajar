<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;

class GuruPiketController extends Controller
{
    /**
     * Akses ditentukan oleh jadwal_piket pada hari berjalan, bukan role user.
     */
    protected function authorizeGuruPiket()
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isPiketHariIni(), 403, 'Akses ditolak. Anda tidak mendapat jadwal piket hari ini.');
    }

    /**
     * Dashboard Guru Piket
     */
    public function dashboard()
    {
        $this->authorizeGuruPiket();

        $today   = now()->toDateString();
        $hariIni = now()->translatedFormat('l');

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        // 1. Total siswa tidak hadir (Sakit / Izin / Alpha) hari ini
        $siswaTidakHadir = PresensiSiswa::whereDate('tanggal', $today)
            ->whereIn('status', ['Sakit', 'Izin', 'Alpha'])
            ->count();

        // 2. Guru tidak hadir / mengajukan izin hari ini
        $guruIzinHariIni = IzinGuru::whereDate('tanggal', $today)->count();

        // 3. Total dispensasi keluar / masuk hari ini
        $dispensasiHariIni = DispensasiSiswa::whereDate('tanggal', $today)->count();

        // 4. Kelas dengan KBM belum terisi (ada sesi KBM hari ini yang jurnalnya belum diisi)
        $jadwalHariIni = JadwalPelajaran::where('hari', $hariIni)
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        $idJadwalHariIni = $jadwalHariIni->pluck('id');
        $jurnalTerisiIds = $idJadwalHariIni->isEmpty()
            ? collect()
            : Jurnal::whereDate('tanggal', $today)
                ->whereIn('id_jadwal', $idJadwalHariIni)
                ->whereNotNull('materi')
                ->where('materi', '!=', '')
                ->pluck('id_jadwal')
                ->unique();

        $kelasKbmBelumTerisi = $jadwalHariIni
            ->groupBy('id_kelas')
            ->filter(fn ($sessions) => $sessions->contains(fn ($s) => ! $jurnalTerisiIds->contains($s->id)))
            ->count();

        // Recent: 5 dispensasi terbaru hari ini
        $dispensasiTerbaru = DispensasiSiswa::with(['siswa.kelas'])
            ->whereDate('tanggal', $today)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        // Recent: daftar guru izin hari ini
        $izinGuruHariIni = IzinGuru::with('user')
            ->whereDate('tanggal', $today)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $izinPendingPiket = IzinGuru::where('status', IzinGuru::STATUS_PENDING_PIKET)->count();

        return view('piket.dashboard', compact(
            'today',
            'hariIni',
            'siswaTidakHadir',
            'guruIzinHariIni',
            'dispensasiHariIni',
            'kelasKbmBelumTerisi',
            'dispensasiTerbaru',
            'izinGuruHariIni',
            'izinPendingPiket',
        ));
    }

    /**
     * Presensi Guru Harian
     */
    public function presensiGuru()
    {
        $this->authorizeGuruPiket();
        return view('piket.presensi_guru');
    }

    /**
     * Jurnal KBM Harian — daftar jurnal untuk semua kelas hari ini
     */
    public function jurnalKBM(Request $request)
    {
        $this->authorizeGuruPiket();

        $today = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);

        $dataJurnal = Jurnal::with([
            'guru',
            'guruPengganti',
            'jadwal.guru',
            'jadwal.mapel',
            'jadwal.kelas',
            'jadwal.jamPelajaran',
        ])
        ->whereDate('tanggal', $tanggal)
        ->orderBy('tanggal', 'desc')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($jurnal) use ($today) {
            // Tambah flag editable: hanya bisa edit jika tanggal jurnal = hari ini
            $jurnal->is_editable = $jurnal->tanggal === $today;
            return $jurnal;
        });

        $gurus = \App\Models\User::orderBy('nama')->get();

        return view('piket.jurnal', compact('dataJurnal', 'tanggal', 'today', 'gurus'));
    }

    /**
     * Presensi Siswa Harian - Tampilkan form input presensi
     */
    public function presensiSiswa(Request $request)
    {
        $this->authorizeGuruPiket();

        $today = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);
        $idKelas = $request->get('id_kelas');

        // Ambil daftar kelas untuk dropdown filter
        $kelasList = Kelas::orderBy('nama_kelas')->get();

        // Ambil siswa berdasarkan kelas yang dipilih
        $siswaQuery = Siswa::with('kelas')
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama');

        if ($idKelas) {
            $siswaQuery->where('id_kelas', $idKelas);
        }

        $dataSiswa = $siswaQuery->get();

        // Ambil presensi yang sudah ada untuk tanggal & kelas tsb
        $presensiExisting = collect();
        if ($idKelas) {
            $presensiExisting = PresensiSiswa::where('tanggal', $tanggal)
                ->where('id_kelas', $idKelas)
                ->get()
                ->keyBy('id_siswa');
        }

        return view('piket.presensi_siswa', compact(
            'kelasList',
            'dataSiswa',
            'presensiExisting',
            'tanggal',
            'today',
            'idKelas'
        ));
    }

    /**
     * Simpan Presensi Siswa Harian
     */
    public function storePresensiSiswa(Request $request)
    {
        $this->authorizeGuruPiket();

        $validated = $request->validate([
            'tanggal'    => 'required|date',
            'id_kelas'   => 'required|exists:kelas,id',
            'presensi'   => 'required|array',
            'presensi.*.id_siswa' => 'required|exists:siswa,id',
            'presensi.*.status'   => 'required|in:Hadir,Sakit,Izin,Alpha',
            'presensi.*.keterangan' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        foreach ($validated['presensi'] as $item) {
            // Hindari galat unique [id_siswa, tanggal] saat baris lama ter-soft delete:
            // temukan termasuk baris trashed, lalu restore & perbarui baris yang sama.
            $presensi = PresensiSiswa::withTrashed()->firstOrNew([
                'id_siswa' => $item['id_siswa'],
                'tanggal'  => $validated['tanggal'],
            ]);

            if ($presensi->trashed()) {
                $presensi->restore();
            }

            $presensi->fill([
                'id_kelas'      => $validated['id_kelas'],
                'status'        => $item['status'],
                'keterangan'    => $item['keterangan'] ?? null,
                'id_guru_piket' => $user->id,
            ])->save();
        }

        return redirect()->route('piket.presensi-siswa', [
            'tanggal'   => $validated['tanggal'],
            'id_kelas'  => $validated['id_kelas'],
        ])->with('success', 'Presensi siswa berhasil disimpan.');
    }
}