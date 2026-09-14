<?php

namespace App\Http\Controllers;

use App\Models\LaporanKendala;
use App\Models\PengaturanJadwal;
use App\Models\User;
use Illuminate\Http\Request;

class HelpController extends Controller
{
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

        if (! $noWaKontak) {
            $noWaKontak = PengaturanJadwal::noWaKepsek();
        }
        if (! $namaKontak) {
            $namaKontak = 'Admin IT';
        }

        // Laporan kendala milik pengguna yang sedang login (untuk pelacakan status).
        $authUser = $request->user();
        $kendalaSaya = $authUser instanceof User
            ? LaporanKendala::where('user_id', $authUser->id)->latest()->take(5)->get()
            : collect();

        return view('bantuan.index', [
            'namaKontak' => $namaKontak,
            'noWaKontak' => $noWaKontak,
            'authUser' => $authUser,
            'kendalaSaya' => $kendalaSaya,
        ]);
    }

    /**
     * Simpan laporan kendala / bug report dari pengguna yang sedang login.
     */
    public function storeKendala(Request $request)
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'prioritas' => ['nullable', 'in:low,medium,high'],
            'foto_bukti' => ['nullable', 'image', 'max:5120'],
        ]);

        $kendala = new LaporanKendala;
        $kendala->user_id = $user->id;
        $kendala->judul = $data['judul'];
        $kendala->deskripsi = $data['deskripsi'];
        $kendala->prioritas = $data['prioritas'] ?? LaporanKendala::PRIORITAS_MEDIUM;
        $kendala->status = LaporanKendala::STATUS_PENDING;

        if ($request->hasFile('foto_bukti')) {
            $kendala->foto_bukti = $request->file('foto_bukti')->store('kendala_foto', 'public');
        }

        $kendala->save();

        return back()->with('success', 'Laporan kendala berhasil dikirim. Tim IT akan segera menindaklanjuti.');
    }
}
