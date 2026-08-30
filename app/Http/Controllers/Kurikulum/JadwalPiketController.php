<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalPiket;
use App\Models\User;
use Illuminate\Http\Request;

class JadwalPiketController extends Controller
{
    /**
     * Proteksi server-side: Admin & Kurikulum
     */
    protected function authorizeKurikulum()
    {
        $user = auth()->user();
        $role = $user ? $user->role : null;

        $isAllowed = in_array($role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu']);
        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Jadwal Piket.');
    }

    protected function authorizeManage()
    {
        $user = auth()->user();
        $isAllowed = $user && in_array($user->role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'admin_tu']);

        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengubah Jadwal Piket.');
    }

    /**
     * Menampilkan daftar Jadwal Piket Guru per hari
     */
    public function index(Request $request)
    {
        $this->authorizeKurikulum();

        $hariList = JadwalPiket::HARI_LIST;

        $allJadwal = JadwalPiket::with('user')
            ->orderBy('id', 'asc')
            ->get();

        $jadwalByHari = [];
        foreach ($hariList as $hari) {
            $jadwalByHari[$hari] = $allJadwal->where('hari', $hari)->values();
        }

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $user = auth()->user();
        $canManage = $user && in_array($user->role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'admin_tu']);

        // ID guru yang sudah terpilih per hari (untuk pre-check checkbox)
        $selectedByHari = [];
        foreach ($hariList as $hari) {
            $selectedByHari[$hari] = $jadwalByHari[$hari]->pluck('user_id')->toArray();
        }

        return view('kurikulum.jadwal_piket.index', compact(
            'hariList', 'jadwalByHari', 'guruList', 'allJadwal', 'selectedByHari', 'canManage'
        ));
    }

    /**
     * Form Halaman Terpisah: Tambah Petugas Piket baru
     */
    public function create(Request $request)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        $selectedHari = $request->get('hari', 'Senin');
        if (!in_array($selectedHari, $hariList)) {
            $selectedHari = 'Senin';
        }

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $assignedGuruIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('user_id')
            ->toArray();

        return view('kurikulum.jadwal_piket.create', compact(
            'hariList', 'selectedHari', 'guruList', 'assignedGuruIds'
        ));
    }

    /**
     * Form Halaman Terpisah: Edit Petugas Piket per hari
     */
    public function edit($hari)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        if (!in_array($hari, $hariList)) {
            $hari = 'Senin';
        }

        $selectedHari = $hari;

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $assignedGuruIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('user_id')
            ->toArray();

        return view('kurikulum.jadwal_piket.edit', compact(
            'hariList', 'selectedHari', 'guruList', 'assignedGuruIds'
        ));
    }

    /**
     * Menyimpan data penugasan piket per hari (sync)
     */
    public function store(Request $request)
    {
        $this->authorizeManage();

        // Harmonize guru_ids / user_ids / user_id
        if (!$request->has('guru_ids') && $request->has('user_ids')) {
            $request->merge(['guru_ids' => (array)$request->user_ids]);
        } elseif (!$request->has('guru_ids') && $request->has('user_id')) {
            $request->merge(['guru_ids' => (array)$request->user_id]);
        }

        $request->validate([
            'hari'       => 'required|in:' . implode(',', JadwalPiket::HARI_LIST),
            'guru_ids'   => 'required|array|min:1',
            'guru_ids.*' => 'exists:users,id',
        ], [
            'hari.required'     => 'Hari piket wajib dipilih.',
            'hari.in'           => 'Hari piket tidak valid.',
            'guru_ids.required' => 'Pilih minimal satu guru piket.',
            'guru_ids.array'    => 'Format guru piket tidak valid.',
            'guru_ids.min'      => 'Pilih minimal satu guru piket.',
            'guru_ids.*.exists' => 'Guru yang dipilih tidak ditemukan dalam sistem.',
        ]);

        $hari = $request->hari;
        $guruIds = array_unique(array_filter((array)$request->guru_ids));

        // Hapus data lama hari tersebut HANYA jika data baru valid dan tidak kosong
        JadwalPiket::where('hari', $hari)->delete();

        // Masukkan data baru
        foreach ($guruIds as $userId) {
            JadwalPiket::create([
                'hari'    => $hari,
                'user_id' => $userId,
            ]);
        }

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', 'Petugas piket hari ' . $hari . ' berhasil diperbarui.');
    }

    /**
     * Menghapus penugasan piket satu guru tertentu
     */
    public function destroy($id)
    {
        $this->authorizeManage();

        $jadwal   = JadwalPiket::with('user')->findOrFail($id);
        $namaGuru = $jadwal->user ? $jadwal->user->nama : 'Petugas Piket';
        $hari     = $jadwal->hari;

        $jadwal->delete();

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', "Penugasan piket {$namaGuru} pada hari {$hari} berhasil dihapus.");
    }
}
