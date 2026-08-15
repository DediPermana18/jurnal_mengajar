<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WaliKelasController extends Controller
{
    /**
     * Rekap Absen Siswa Wali Kelas
     */
    public function rekapAbsen()
    {
        return view('walikelas.rekap_absen');
    }

    /**
     * Riwayat Jurnal Kelas
     */
    public function riwayatJurnal()
    {
        return view('walikelas.riwayat_jurnal');
    }

    /**
     * Catatan Siswa Bermasalah
     */
    public function siswaBermasalah()
    {
        return view('walikelas.siswa_bermasalah');
    }
}
