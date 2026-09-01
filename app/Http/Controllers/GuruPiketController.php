<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
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
        return view('piket.dashboard');
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
            PresensiSiswa::updateOrCreate(
                [
                    'id_siswa' => $item['id_siswa'],
                    'tanggal'  => $validated['tanggal'],
                ],
                [
                    'id_kelas'       => $validated['id_kelas'],
                    'status'         => $item['status'],
                    'keterangan'     => $item['keterangan'] ?? null,
                    'id_guru_piket'  => $user->id,
                ]
            );
        }

        return redirect()->route('piket.presensi-siswa', [
            'tanggal'   => $validated['tanggal'],
            'id_kelas'  => $validated['id_kelas'],
        ])->with('success', 'Presensi siswa berhasil disimpan.');
    }
}