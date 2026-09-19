<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JadwalPiket;
use App\Models\ShiftPiket;
use App\Models\User;
use Carbon\Carbon;
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
        $mingguKe = $this->mingguKe($request);

        $allJadwal = JadwalPiket::with(['user', 'shift'])
            ->where('minggu_ke', $mingguKe)
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
        $canManage = $user && in_array($user->effectiveRole(), ['admin', 'admin_kurikulum', 'waka_kurikulum', 'admin_tu']);

        // ID guru yang sudah terpilih per hari (untuk pre-check checkbox)
        $selectedByHari = [];
        foreach ($hariList as $hari) {
            $selectedByHari[$hari] = $jadwalByHari[$hari]->pluck('user_id')->toArray();
        }

        return view('kurikulum.jadwal_piket.index', compact(
            'hariList', 'jadwalByHari', 'guruList', 'allJadwal', 'selectedByHari', 'canManage',
            'mingguKe'
        ));
    }

    /**
     * Form Halaman Terpisah: Tambah Petugas Piket baru
     */
    public function create(Request $request)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        $mingguKe = $this->mingguKe($request);
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

        $shiftList = ShiftPiket::where('is_active', true)->orderBy('urutan')->orderBy('id')->get();

        $jadwalHariIni = JadwalPiket::where('hari', $selectedHari)
            ->where('minggu_ke', $mingguKe);

        $assignedByShift = (clone $jadwalHariIni)
            ->whereNotNull('shift_id')
            ->get()
            ->groupBy('shift_id')
            ->map(fn ($items) => $items->pluck('user_id')->filter()->values()->all())
            ->all();

        $assignedGuruIds = (clone $jadwalHariIni)
            ->pluck('user_id')
            ->toArray();

        $assignedWakaId = (clone $jadwalHariIni)
            ->pluck('waka_user_id')
            ->first();

        $assignedKoordinatorPagiIds = (clone $jadwalHariIni)
            ->pluck('koordinator_pagi_user_id')
            ->filter()
            ->toArray();

        $assignedPetugasPagiIds = (clone $jadwalHariIni)
            ->pluck('petugas_pagi_user_id')
            ->filter()
            ->toArray();

        $assignedKoordinatorSiangIds = (clone $jadwalHariIni)
            ->pluck('koordinator_siang_user_id')
            ->filter()
            ->toArray();

        $assignedPetugasSiangIds = (clone $jadwalHariIni)
            ->pluck('petugas_siang_user_id')
            ->filter()
            ->toArray();

        return view('kurikulum.jadwal_piket.create', compact(
            'hariList', 'selectedHari', 'guruList', 'wakaList',
            'shiftList', 'assignedByShift',
            'mingguKe',
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

        // Harmonize legacy submissions while the dynamic form uses shift_users.
        if (! $request->has('guru_ids') && $request->has('user_ids')) {
            $request->merge(['guru_ids' => (array) $request->user_ids]);
        } elseif (! $request->has('guru_ids') && $request->has('user_id')) {
            $request->merge(['guru_ids' => (array) $request->user_id]);
        }

        $mingguKe = $this->mingguKe($request);
        $request->merge([
            'minggu_ke' => $mingguKe,
        ]);

        $rules = [
            'hari' => 'required|in:'.implode(',', JadwalPiket::HARI_LIST),
            'minggu_ke' => 'required|integer|between:1,4',
            'waka_user_id' => 'nullable|exists:users,id',
            'shift_users' => 'nullable|array',
            'shift_users.*' => 'array',
            'shift_users.*.*' => 'exists:users,id',
            'guru_ids' => 'nullable|array|min:1',
            'guru_ids.*' => 'exists:users,id',
        ];
        $request->validate($rules, [
            'hari.required' => 'Hari piket wajib dipilih.',
            'hari.in' => 'Hari piket tidak valid.',
            'shift_users.*.*.exists' => 'Guru yang dipilih tidak ditemukan dalam sistem.',
            'guru_ids.*.exists' => 'Guru yang dipilih tidak ditemukan dalam sistem.',
        ]);

        $hari = $request->hari;
        $bulan = now()->month;
        $tahun = now()->year;
        $mingguKe = $this->mingguKe($request);
        $shiftUsers = collect($request->input('shift_users', []))
            ->map(fn ($ids) => array_values(array_unique(array_filter((array) $ids))))
            ->filter(fn ($ids) => count($ids) > 0);
        $guruIds = array_unique(array_filter((array) $request->guru_ids));

        if ($shiftUsers->isEmpty() && empty($guruIds)) {
            $errorKey = $request->hasAny(['guru_ids', 'user_ids', 'user_id']) ? 'guru_ids' : 'shift_users';
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => 'Pilih minimal satu guru pada salah satu shift.',
            ]);
        }

        // Guard: penggantian penugasan piket tidak boleh menimpa data testing (kecuali IT/QA).
        $this->authorizeTestingBatch(JadwalPiket::where('hari', $hari)
            ->where('minggu_ke', $mingguKe)
            ->where('is_testing_data', true));

        // Hapus data lama hari tersebut HANYA jika data baru valid dan tidak kosong
        JadwalPiket::where('hari', $hari)
            ->where('minggu_ke', $mingguKe)
            ->delete();

        // Masukkan data baru
        if ($shiftUsers->isNotEmpty()) {
            $wakaId = $request->input('waka_user_id');
            $first = true;
            foreach ($shiftUsers as $shiftId => $userIds) {
                foreach ($userIds as $userId) {
                    JadwalPiket::create([
                        'hari' => $hari,
                        'minggu_ke' => $mingguKe,
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'shift_id' => $shiftId,
                        'user_id' => $userId,
                        'waka_user_id' => $first ? $wakaId : null,
                    ]);
                    $first = false;
                }
            }
        }
        foreach ($guruIds as $userId) {
            JadwalPiket::create([
                'hari' => $hari,
                'minggu_ke' => $mingguKe,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'user_id' => $userId,
            ]);
        }

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', 'Petugas piket hari '.$hari.' berhasil diperbarui.');
    }

    public function shifts()
    {
        $this->authorizeManage();

        return view('kurikulum.jadwal_piket.shifts', [
            'shifts' => ShiftPiket::orderBy('urutan')->orderBy('id')->get(),
        ]);
    }

    protected function mingguKe(Request $request): int
    {
        $now = Carbon::now();
        return max(1, min(4, (int) $request->input('minggu_ke', ceil($now->day / 7))));
    }

    public function storeShift(Request $request)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'maksimal_petugas' => 'required|integer|min:1|max:100',
            'urutan' => 'nullable|integer|min:0|max:1000',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        ShiftPiket::create($data);

        return back()->with('success', 'Shift piket berhasil ditambahkan.');
    }

    public function updateShift(Request $request, ShiftPiket $shift)
    {
        $this->authorizeManage();
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'maksimal_petugas' => 'required|integer|min:1|max:100',
            'urutan' => 'nullable|integer|min:0|max:1000',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $shift->update($data);

        return back()->with('success', 'Shift piket berhasil diperbarui.');
    }

    public function destroyShift(ShiftPiket $shift)
    {
        $this->authorizeManage();
        $shift->delete();

        return back()->with('success', 'Shift piket berhasil dihapus.');
    }

    /**
     * Menghapus penugasan piket satu guru tertentu
     */
    public function destroy($id)
    {
        $this->authorizeManage();

        $jadwal = JadwalPiket::with('user')->findOrFail($id);
        $namaGuru = $jadwal->user ? $jadwal->user->nama : 'Petugas Piket';
        $hari = $jadwal->hari;

        // Guard: hanya IT/QA yang dapat menghapus penugasan piket data testing.
        $this->authorizeTestingMutation($jadwal);

        $jadwal->delete();

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', "Penugasan piket {$namaGuru} pada hari {$hari} berhasil dihapus.");
    }
}
