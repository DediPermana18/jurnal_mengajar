<?php

namespace App\Http\Controllers;

use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    /**
     * Jam operasional layanan bantuan teknis.
     */
    protected function jamOperasional(): array
    {
        return [
            'Senin – Jumat'        => '07.00 – 16.00 WIB',
            'Sabtu'                => '07.30 – 12.30 WIB',
            'Minggu / Hari libur'  => 'Layanan terbatas (lewat pesan)',
        ];
    }

    /**
     * Halaman Pusat Bantuan & Panduan Penggunaan WebJournal.
     */
    public function index(Request $request)
    {
        // Kontak Admin IT / Tim Pengembang: prioritas admin dengan nomor HP terisi.
        $adminIt = User::where('role', User::ROLE_ADMIN)
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->orderBy('id')
            ->first();

        $namaKontak = $adminIt?->nama;
        $noWaKontak = $adminIt?->noHpInternasional();

        if (!$noWaKontak) {
            $noWaKontak = PengaturanJadwal::noWaKepsek();
        }
        if (!$namaKontak) {
            $namaKontak = 'Admin IT';
        }

        return view('bantuan.index', [
            'namaKontak'     => $namaKontak,
            'noWaKontak'     => $noWaKontak,
            'jamOperasional' => $this->jamOperasional(),
        ]);
    }
}