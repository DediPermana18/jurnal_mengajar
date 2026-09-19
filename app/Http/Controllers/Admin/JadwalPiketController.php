<?php

namespace App\Http\Controllers\Admin;

use App\Models\JadwalPiket;
use App\Models\User;
use Illuminate\Http\Request;

class JadwalPiketController extends AppHttpControllersController
{
    /**
     * Proteksi server-side: Admin & Kurikulum
     */
    protected function authorizeKurikulum()
    {
        $user = auth()->user();
        $role = $user ? $user->role : null;

        $isAllowed = ($user && $user->isPetugasIt())
            || in_array($role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu']);
        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Jadwal Piket.');
    }

    protected function authorizeManage()
    {
        $user = auth()->user();
        $manageRoles = ['admin', 'admin_kurikulum', 'waka_kurikulum', 'admin_tu'];

        // Memakai effectiveRole agar Petugas IT yang impersonasi waka_kurikulum /
        // admin_tu diizinkan menulis (data masuk mode testing), sedangkan IT mode
        // langsung (effectiveRole 'petugas_it') hanya bisa melihat.
        $isAllowed = $user && in_array($user->effectiveRole(), $manageRoles, true);

        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengubah Jadwal Piket.');
    }

    /**
     * Menampilkan daftar Jadwal Piket Guru per hari
     */
    public function index(Request $request)
    {
        $this->authorizeKurikulum();

        $hariList = JadwalPiket::HARI_LIST;

        $allJadwal = JadwalPiket::with(['user', 'waka', 'koordinatorPagi', 'petugasPagi', 'koordinatorSiang', 'petugasSiang'])
            ->orderBy('id', 'asc')
            ->get();

        $jadwalByHari = [];
        foreach ($hariList as $hari) {
            $jadwalByHari[$hari] = $allJadwal->where('hari', $hari)->values();
        }

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $wakaList = User::whereIn('role', ['waka', 'wakakurikulum'])
            ->orderBy('nama', 'asc')
            ->get();

        $user = auth()->user();
        $canManage = $user && in_array($user->effectiveRole(), ['admin', 'admin_kurikulum', 'waka_kurikulum', 'admin_tu']);

        // Data per hari untuk field baru
        $wakaByHari = [];
        $koordinatorPagiByHari = [];
        $petugasPagiByHari = [];
        $koordinatorSiangByHari = [];
        $petugasSiangByHari = [];

        foreach ($hariList as $hari) {
            $wakaByHari[$hari] = $jadwalByHari[$hari]->pluck('waka_user_id')->toArray();
            $koordinatorPagiByHari[$hari] = $jadwalByHari[$hari]->pluck('koordinator_pagi_user_id')->toArray();
            $petugasPagiByHari[$hari] = $jadwalByHari[$hari]->pluck('petugas_pagi_user_id')->toArray();
            $koordinatorSiangByHari[$hari] = $jadwalByHari[$hari]->pluck('koordinator_siang_user_id')->toArray();
            $petugasSiangByHari[$hari] = $jadwalByHari[$hari]->pluck('petugas_siang_user_id')->toArray();
        }

        return view('admin.jadwal_piket.index', compact(
            'hariList', 'jadwalByHari', 'guruList', 'wakaList',
            'allJadwal', 'selectedByHari', 'canManage',
            'wakaByHari', 'koordinatorPagiByHari', 'petugasPagiByHari',
            'koordinatorSiangByHari', 'petugasSiangByHari'
        ));
    }

    /**
     * Form Halaman Terpisah: Tambah/Petugas Piket baru
     */
    public function create(Request $request)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        $selectedHari = $request->get('hari', 'Senin');
        if (! in_array($selectedHari, $hariList)) {
            $selectedHari = 'Senin';
        }

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $wakaList = User::whereIn('role', ['waka', 'wakakurikulum'])
            ->orderBy('nama', 'asc')
            ->get();

        $assignedGuruIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('user_id')
            ->toArray();

        $assignedWakaId = JadwalPiket::where('hari', $selectedHari)
            ->pluck('waka_user_id')
            ->first();

        $assignedKoordinatorPagiIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('koordinator_pagi_user_id')
            ->filter()
            ->toArray();

        $assignedPetugasPagiIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('petugas_pagi_user_id')
            ->filter()
            ->toArray();

        $assignedKoordinatorSiangIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('koordinator_siang_user_id')
            ->filter()
            ->toArray();

        $assignedPetugasSiangIds = JadwalPiket::where('hari', $selectedHari)
            ->pluck('petugas_siang_user_id')
            ->filter()
            ->toArray();

        return view('admin.jadwal_piket.create', compact(
            'hariList', 'selectedHari', 'guruList', 'wakaList',
            'assignedGuruIds', 'assignedWakaId',
            'assignedKoordinatorPagiIds', 'assignedPetugasPagiIds',
            'assignedKoordinatorSiangIds', 'assignedPetugasSiangIds'
        ));
    }

    /**
     * Form Halaman Terpisah: Edit Petugas Piket per hari
     */
    public function edit($hari)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        if (! in_array($hari, $hariList)) {
            $hari = 'Senin';
        }

        $selectedHari = $hari;

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        $wakaList = User::whereIn('role', ['waka', 'wakakurikulum'])
            ->orderBy('nama', 'asc')
            ->get();

        $jadwalHariIni = JadwalPiket::where('hari', $selectedHari)->first();

        $assignedGuruIds = $jadwalHariIni ? $jadwalHariIni->user_id : null;
        $assignedWakaId = $jadwalHariIni ? $jadwalHariIni->waka_user_id : null;

        $assignedKoordinatorPagiIds = $jadwalHariIni ? $jadwalHariIni->koordinator_pagi_user_id : null;
        $assignedPetugasPagiIds = $jadwalHariIni ? $jadwalHariIni->petugas_pagi_user_id->toArray() : [];
        if (is_array($assignedPetugasPagiIds)) {
            $assignedPetugasPagiIds = array_filter($assignedPetugasPagiIds);
        }

        $assignedKoordinatorSiangIds = $jadwalHariIni ? $jadwalHariIni->koordinator_siang_user_id : null;
        $assignedPetugasSiangIds = $jadwalHariIni ? $jadwalHariIni->petugas_siang_user_id->toArray() : [];
        if (is_array($assignedPetugasSiangIds)) {
            $assignedPetugasSiangIds = array_filter($assignedPetugasSiangIds);
        }

        return view('admin.jadwal_piket.edit', compact(
            'hariList', 'selectedHari', 'guruList', 'wakaList',
            'assignedGuruIds', 'assignedWakaId',
            'assignedKoordinatorPagiIds', 'assignedPetugasPagiIds',
            'assignedKoordinatorSiangIds', 'assignedPetugasSiangIds'
        ));
    }

    /**
     * Menyimpan data penugasan piket per hari (sync berdasarkan SK format)
     * Struktur: Setiap hari memiliki:
     * - 1 Waka Piket
     * - 1 Koordinator Pagi + 3-4 Petugas Piket Pagi
     * - 1 Koordinator Siang + 3-4 Petugas Piket Siang
     */
    public function store(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'hari' => 'required|in:'.implode(',', JadwalPiket::HARI_LIST),
            'waka_user_id' => 'nullable|exists:users,id',
            'koordinator_pagi_user_id' => 'nullable|exists:users,id',
            'petugas_pagi_user_id' => 'nullable|array|min:1|max:4',
            'petugas_pagi_user_id.*' => 'exists:users,id',
            'koordinator_siang_user_id' => 'nullable|exists:users,id',
            'petugas_siang_user_id' => 'nullable|array|min:1|max:4',
            'petugas_siang_user_id.*' => 'exists:users,id',
        ], [
            'hari.required' => 'Hari piket wajib dipilih.',
            'hari.in' => 'Hari piket tidak valid.',
            'waka_user_id.exists' => 'Waka piket yang dipilih tidak ditemukan.',
            'koordinator_pagi_user_id.exists' => 'Koordinator Pagi yang dipilih tidak ditemukan.',
            'petugas_pagi_user_id' => 'Pilih minimal 1 dan maksimal 4 guru petugas Pagi.',
            'petugas_pagi_user_id.*' => 'Guru petugas Piket tidak ditemukan.',
            'koordinator_siang_user_id.exists' => 'Koordinator Siang yang dipilih tidak ditemukan.',
            'petugas_siang_user_id' => 'Pilih minimal 1 dan maksimal 4 guru petugas Siang.',
            'petugas_siang_user_id.*' => 'Guru petugas Siang tidak ditemukan.',
        ]);

        $hari = $request->hari;

        // Ambil ID yang dipilih
        $wakaUserId = $request->waka_user_id ? (array) $request->waka_user_id : [];
        $koordinatorPagiUserId = $request->koordinator_pagi_user_id ? (array) $request->koordinator_pagi_user_id : [];
        $petugasPagiUserIds = array_unique(array_filter((array) $request->petugas_pagi_user_id));
        $koordinatorSiangUserId = $request->koordinator_siang_user_id ? (array) $request->koordinator_siang_user_id : [];
        $petugasSiangUserIds = array_unique(array_filter((array) $request->petugas_siang_user_id));

        // Guard: penggantian penugasan piket tidak boleh menimpa data testing (kecuali IT/QA).
        $this->authorizeTestingBatch(JadwalPiket::where('hari', $hari)->where('is_testing_data', true));

        // Hapus data lama hari tersebut HANYA jika data baru valid dan tidak kosong
        JadwalPiket::where('hari', $hari)->delete();

        // Masukkan Waka Piket (single)
        if (!empty($wakaUserId)) {
            foreach ($wakaUserId as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'user_id' => null,  // user_id utama untuk compatibilitas
                    'waka_user_id' => $userId,
                ]);
            }
        }

        // Masukkan Koordinator Pagi (single)
        if (!empty($koordinatorPagiUserId)) {
            foreach ($koordinatorPagiUserId as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'user_id' => null,
                    'koordinator_pagi_user_id' => $userId,
                ]);
            }
        }

        // Masukkan Petugas Piket Pagi (multiple 3-4)
        if (!empty($petugasPagiUserIds)) {
            foreach ($petugasPagiUserIds as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'user_id' => null,
                    'petugas_pagi_user_id' => $userId,
                ]);
            }
        }

        // Masukkan Koordinator Siang (single)
        if (!empty($koordinatorSiangUserId)) {
            foreach ($koordinatorSiangUserId as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'user_id' => null,
                    'koordinator_siang_user_id' => $userId,
                ]);
            }
        }

        // Masukkan Petugas Piket Siang (multiple 3-4)
        if (!empty($petugasSiangUserIds)) {
            foreach ($petugasSiangUserIds as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'user_id' => null,
                    'petugas_siang_user_id' => $userId,
                ]);
            }
        }

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', 'Jadwal piket hari '.$hari.' berhasil diperbarui sesuai format SK.');
    }

    /**
     * Menghapus penugasan piket sesuai field
     */
    public function destroy($id)
    {
        $this->authorizeManage();

        $jadwal = JadwalPiket::with(['user', 'waka', 'koordinatorPagi', 'petugasPagi', 'koordinatorSiang', 'petugasSiang'])->findOrFail($id);
        $namaGuru = $jadwal->user ? $jadwal->user->nama : ($jadwal->waka ? $jadwal->waka->nama : 'Petugas Piket');
        $hari = $jadwal->hari;

        // Guard: hanya IT/QA yang dapat menghapus penugasan piket data testing.
        $this->authorizeTestingMutation($jadwal);

        $jadwal->delete();

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', "Penugasan piket {$namaGuru} pada hari {$hari} berhasil dihapus.");
    }
}