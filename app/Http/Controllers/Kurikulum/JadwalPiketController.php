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

        // Waka Piket: role legacy ('waka'/'wakakurikulum') ATAU skema baru
        // role 'admin' + sub_role 'waka_piket' (ditambah lewat /admin/users).
        $wakaList = User::where(function ($query) {
            $query->whereIn('role', ['waka', 'wakakurikulum'])
                ->orWhere(function ($query) {
                    $query->where('role', User::ROLE_ADMIN)
                        ->where('sub_role', 'waka_piket');
                });
        })
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
     * (menggunakan form yang sama dengan create — include create.blade.php).
     */
    public function edit(Request $request, $hari)
    {
        $this->authorizeManage();

        $hariList = JadwalPiket::HARI_LIST;
        if (! in_array($hari, $hariList)) {
            $hari = 'Senin';
        }

        $selectedHari = $hari;
        $mingguKe = $this->mingguKe($request);

        $guruList = User::where('role', 'guru')
            ->orderBy('nama', 'asc')
            ->get();

        // Waka Piket: role legacy ('waka'/'wakakurikulum') ATAU skema baru
        // role 'admin' + sub_role 'waka_piket'.
        $wakaList = User::where(function ($query) {
            $query->whereIn('role', ['waka', 'wakakurikulum'])
                ->orWhere(function ($query) {
                    $query->where('role', User::ROLE_ADMIN)
                        ->where('sub_role', 'waka_piket');
                });
        })
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
            ->filter()
            ->values()
            ->all();

        $assignedWakaId = (clone $jadwalHariIni)->pluck('waka_user_id')->filter()->first();

        $assignedKoordinatorPagiIds = (clone $jadwalHariIni)->pluck('koordinator_pagi_user_id')->filter()->values()->all();
        $assignedPetugasPagiIds = (clone $jadwalHariIni)->pluck('petugas_pagi_user_id')->filter()->values()->all();
        $assignedKoordinatorSiangIds = (clone $jadwalHariIni)->pluck('koordinator_siang_user_id')->filter()->values()->all();
        $assignedPetugasSiangIds = (clone $jadwalHariIni)->pluck('petugas_siang_user_id')->filter()->values()->all();

        return view('kurikulum.jadwal_piket.edit', compact(
            'hariList', 'selectedHari', 'mingguKe', 'guruList', 'wakaList', 'shiftList',
            'assignedGuruIds', 'assignedWakaId', 'assignedByShift',
            'assignedKoordinatorPagiIds', 'assignedPetugasPagiIds',
            'assignedKoordinatorSiangIds', 'assignedPetugasSiangIds'
        ));
    }

    /**
     * Menyimpan data penugasan piket per hari (sync).
     *
     * Mendukung DUA format kiriman:
     * 1. Format SK (legacy) — form Pagi & Siang: waka_user_id,
     *    koordinator_pagi_user_id, petugas_pagi_user_id[],
     *    koordinator_siang_user_id, petugas_siang_user_id[].
     *    Guru pada petugas_pagi dan petugas_siang TIDAK boleh sama
     *    (mutual exclusion; divalidasi server sebagai fallback JS di form).
     * 2. Format shift dinamis — shift_users[shiftId][] / guru_ids.
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
            'koordinator_pagi_user_id' => 'nullable|exists:users,id',
            'koordinator_siang_user_id' => 'nullable|exists:users,id',
            'petugas_pagi_user_id' => 'nullable|array',
            'petugas_pagi_user_id.*' => 'exists:users,id',
            'petugas_siang_user_id' => 'nullable|array',
            'petugas_siang_user_id.*' => 'exists:users,id',
        ];
        $request->validate($rules, [
            'hari.required' => 'Hari piket wajib dipilih.',
            'hari.in' => 'Hari piket tidak valid.',
            'shift_users.*.*.exists' => 'Guru yang dipilih tidak ditemukan dalam sistem.',
            'guru_ids.*.exists' => 'Guru yang dipilih tidak ditemukan dalam sistem.',
            'petugas_pagi_user_id.*.exists' => 'Guru petugas Pagi tidak ditemukan dalam sistem.',
            'petugas_siang_user_id.*.exists' => 'Guru petugas Siang tidak ditemukan dalam sistem.',
        ]);

        $hari = $request->hari;
        $bulan = now()->month;
        $tahun = now()->year;
        $mingguKe = $this->mingguKe($request);

        // Format SK (legacy): field checkbox petugas Pagi/Siang hadir di kiriman.
        $isLegacySk = $request->has('petugas_pagi_user_id') || $request->has('petugas_siang_user_id');

        $pagiIds = array_values(array_unique(array_filter((array) $request->input('petugas_pagi_user_id', []))));
        $siangIds = array_values(array_unique(array_filter((array) $request->input('petugas_siang_user_id', []))));

        // Validasi mutual exclusion: satu guru tidak boleh di shift Pagi & Siang.
        if (! empty(array_intersect($pagiIds, $siangIds))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'petugas_pagi_user_id' => 'Guru yang sama tidak dapat bertugas di shift Pagi dan Siang bersamaan.',
            ]);
        }

        // Validasi mutual exclusion: Koordinator Piket (Pagi/Siang) tidak boleh
        // merangkap menjadi Petugas Piket biasa (fallback server untuk JS form).
        $koordinatorIds = array_values(array_unique(array_filter([
            $request->input('koordinator_pagi_user_id'),
            $request->input('koordinator_siang_user_id'),
        ])));

        if (! empty($koordinatorIds) && ! empty(array_intersect(array_merge($pagiIds, $siangIds), $koordinatorIds))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'koordinator_pagi_user_id' => 'Guru yang menjadi Koordinator Piket tidak dapat dipilih sebagai Petugas Piket biasa.',
            ]);
        }

        if ($isLegacySk) {
            $wakaId = $request->input('waka_user_id');
            $koordinatorPagiId = $request->input('koordinator_pagi_user_id');
            $koordinatorSiangId = $request->input('koordinator_siang_user_id');

            $adaPenugasan = ! empty($pagiIds) || ! empty($siangIds)
                || ! empty($wakaId) || ! empty($koordinatorPagiId) || ! empty($koordinatorSiangId);

            if (! $adaPenugasan) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'petugas_pagi_user_id' => 'Pilih minimal satu guru pada salah satu shift.',
                ]);
            }
        } else {
            $shiftUsers = collect($request->input('shift_users', []))
                ->map(fn ($ids) => array_values(array_unique(array_filter((array) $ids))))
                ->filter(fn ($ids) => count($ids) > 0);
            $guruIds = array_unique(array_filter((array) $request->guru_ids));

            // Mutual exclusion antar sesi Pagi & Siang (format shift dinamis):
            // sesi dikenali dari nama shift (SK sekolah memakai 'Pagi'/'Siang').
            $shiftPagiId = ShiftPiket::where('is_active', true)
                ->whereRaw('LOWER(nama) LIKE ?', ['pagi%'])
                ->orderBy('urutan')->value('id');
            $shiftSiangId = ShiftPiket::where('is_active', true)
                ->whereRaw('LOWER(nama) LIKE ?', ['siang%'])
                ->orderBy('urutan')->value('id');

            if ($shiftPagiId && $shiftSiangId) {
                $selectedPagi = array_values(array_unique(array_filter((array) ($shiftUsers[$shiftPagiId] ?? []))));
                $selectedSiang = array_values(array_unique(array_filter((array) ($shiftUsers[$shiftSiangId] ?? []))));

                if (! empty(array_intersect($selectedPagi, $selectedSiang))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'shift_users' => 'Guru yang sama tidak dapat bertugas di shift Pagi dan Siang bersamaan.',
                    ]);
                }
            }

            // Validasi mutual exclusion: Koordinator Piket tidak boleh merangkap
            // menjadi Petugas Piket biasa (format shift dinamis).
            $petugasShiftIds = $shiftUsers->flatten()->values()->all();
            if (! empty($koordinatorIds) && ! empty(array_intersect($petugasShiftIds, $koordinatorIds))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'koordinator_pagi_user_id' => 'Guru yang menjadi Koordinator Piket tidak dapat dipilih sebagai Petugas Piket biasa.',
                ]);
            }

            if ($shiftUsers->isEmpty() && empty($guruIds)) {
                $errorKey = $request->hasAny(['guru_ids', 'user_ids', 'user_id']) ? 'guru_ids' : 'shift_users';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $errorKey => 'Pilih minimal satu guru pada salah satu shift.',
                ]);
            }
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
        if ($isLegacySk) {
            // Format SK: satu baris per petugas, masing-masing mengisi kolom
            // waka / koordinator / petugas sesuai perannya (skema jadwal_piket).
            foreach ((array) $request->input('waka_user_id') as $userId) {
                if (! $userId) {
                    continue;
                }
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, $userId, ['waka_user_id' => $userId]);
            }
            if ($koordinatorPagiId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, $koordinatorPagiId, ['koordinator_pagi_user_id' => $koordinatorPagiId]);
            }
            foreach ($pagiIds as $userId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, $userId, ['petugas_pagi_user_id' => $userId]);
            }
            if ($koordinatorSiangId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, $koordinatorSiangId, ['koordinator_siang_user_id' => $koordinatorSiangId]);
            }
            foreach ($siangIds as $userId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, $userId, ['petugas_siang_user_id' => $userId]);
            }
        } elseif ($shiftUsers->isNotEmpty()) {
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

            // Koordinator Piket (Pagi/Siang) — format SK: simpan sebagai baris
            // terpisah dengan kolom koordinator_* terisi agar turut menerima
            // notifikasi WA tahap pengajuan izin.
            $koordinatorPagiId = $request->input('koordinator_pagi_user_id');
            $koordinatorSiangId = $request->input('koordinator_siang_user_id');

            if ($koordinatorPagiId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, (int) $koordinatorPagiId, ['koordinator_pagi_user_id' => (int) $koordinatorPagiId]);
            }
            if ($koordinatorSiangId) {
                $this->buatBarisJadwal($hari, $mingguKe, $bulan, $tahun, (int) $koordinatorSiangId, ['koordinator_siang_user_id' => (int) $koordinatorSiangId]);
            }
        } else {
            foreach ($guruIds as $userId) {
                JadwalPiket::create([
                    'hari' => $hari,
                    'minggu_ke' => $mingguKe,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'user_id' => $userId,
                ]);
            }
        }

        return redirect()->route('kurikulum.jadwal-piket.index')
            ->with('success', 'Petugas piket hari '.$hari.' berhasil diperbarui.');
    }

    /**
     * Membuat satu baris `jadwal_piket` untuk format SK. Karena kolom
     * `user_id` wajib terisi (NOT NULL), baris memakai user_id = guru yang
     * ditugaskan, dan kolom peran (waka/koordinator/petugas) diisi di $kolom.
     */
    protected function buatBarisJadwal(string $hari, int $mingguKe, int $bulan, int $tahun, int $userId, array $kolom): JadwalPiket
    {
        return JadwalPiket::create(array_merge([
            'hari' => $hari,
            'minggu_ke' => $mingguKe,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'user_id' => $userId,
        ], $kolom));
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
