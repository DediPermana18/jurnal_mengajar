<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GuruPiketController extends Controller
{
    /**
     * Block akses jika bukan Guru Piket
     */
    protected function authorizeGuruPiket()
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_if($role !== 'guru_piket', 403, 'Akses ditolak. Halaman ini khusus untuk Guru Piket.');
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

        $today = Carbon::today()->toDateString();
        $tanggal = $request->get('tanggal', $today);

        $dataJurnal = Jurnal::with([
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

        return view('piket.jurnal', compact('dataJurnal', 'tanggal', 'today'));
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
