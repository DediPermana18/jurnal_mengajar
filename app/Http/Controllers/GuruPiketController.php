<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
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
     * Presensi Siswa Harian
     */
    public function presensiSiswa()
    {
        $this->authorizeGuruPiket();
        return view('piket.presensi_siswa');
    }
}
