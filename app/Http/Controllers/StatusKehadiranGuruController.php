<?php

namespace App\Http\Controllers;

use App\Models\StatusKehadiranGuru;
use App\Models\User;
use App\Services\StatusKehadiranService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * Portal Guru Piket — Status Kehadiran Guru.
 *
 * Menampilkan daftar seluruh guru beserta status kehadiran hariannya,
 * dengan fallback 'Hadir' bagi guru yang tidak memiliki record.
 * Mendukung filter tanggal & pencarian nama/NIP, plus override manual
 * (update cepat) oleh Guru Piket.
 */
class StatusKehadiranGuruController extends Controller
{
    /**
     * Akses serupa portal Guru Piket: Guru Piket (jadwal hari ini), Admin,
     * atau Petugas IT (termasuk saat "Switch View As" guru_piket).
     */
    protected function authorizePiket(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $allowed = $user->isPetugasIt()
            || $user->activeRole() === 'guru_piket'
            || in_array($user->role, [User::ROLE_ADMIN], true)
            || $user->isPiketHariIni();

        abort_unless($allowed, 403, 'Akses ditolak. Hanya Guru Piket yang dapat mengelola status kehadiran guru.');
    }

    /**
     * Halaman Status Kehadiran Guru — tabel seluruh guru + status harian.
     */
    public function index(Request $request)
    {
        $this->authorizePiket();

        $tanggal = $request->get('tanggal', now()->toDateString());
        $cari = trim((string) $request->get('q'));
        $filterStatus = $request->get('status');

        // Sinkronkan otomatis dari izin yang sudah disetujui pada tanggal tsb
        // (idempotent; tidak menimpa override manual).
        StatusKehadiranService::sinkronkanTanggal($tanggal);

        $kehadiranMap = StatusKehadiranGuru::whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('user_id');

        $guruList = User::where('role', User::ROLE_GURU)
            ->where('is_active', true)
            ->orderBy('nama')
            ->get()
            ->map(function (User $guru) use ($kehadiranMap) {
                $record = $kehadiranMap->get($guru->id);

                // Fallback state: tanpa record -> dianggap Hadir.
                $guru->kehadiran = $record ?? new StatusKehadiranGuru([
                    'user_id' => $guru->id,
                    'status' => StatusKehadiranGuru::STATUS_HADIR,
                ]);

                return $guru;
            })
            ->when($cari !== '', fn ($items) => $items->filter(
                fn (User $guru) => str_contains(strtolower((string) $guru->nama), strtolower($cari))
                    || ($guru->nip && str_contains(strtolower((string) $guru->nip), strtolower($cari)))
            ))
            ->when($filterStatus, fn ($items) => $items->filter(
                fn (User $guru) => $guru->kehadiran->status === $filterStatus
            ))
            ->values();

        $stats = [
            'total' => $guruList->count(),
            StatusKehadiranGuru::STATUS_HADIR => $guruList->where('kehadiran.status', StatusKehadiranGuru::STATUS_HADIR)->count(),
            StatusKehadiranGuru::STATUS_SAKIT => $guruList->where('kehadiran.status', StatusKehadiranGuru::STATUS_SAKIT)->count(),
            StatusKehadiranGuru::STATUS_IZIN => $guruList->where('kehadiran.status', StatusKehadiranGuru::STATUS_IZIN)->count(),
            StatusKehadiranGuru::STATUS_DINAS_LUAR => $guruList->where('kehadiran.status', StatusKehadiranGuru::STATUS_DINAS_LUAR)->count(),
        ];

        $perPage = 25;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $items = $guruList->forPage($currentPage, $perPage)->values();

        $gurus = new LengthAwarePaginator($items, $guruList->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('piket.status_kehadiran_guru', compact('tanggal', 'cari', 'filterStatus', 'gurus', 'stats'));
    }

    /**
     * Daftar ID "user aktif" yang TIDAK boleh di-override status kehadirannya:
     * - auth()->id()                     → user login asli; dan
     * - session('impersonate_target_id') → target impersonasi saat ini; dan
     * - session('simulated_user_id')     → (fallback) key simulasi lain.
     */
    protected function activeUserIds(): array
    {
        $ids = array_filter([
            auth()->id(),
            session('impersonate_target_id'),
            session('simulated_user_id'),
        ], fn ($v) => $v !== null && $v !== '' && (int) $v > 0);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Manual override / update cepat status kehadiran guru oleh Guru Piket
     * (mis. perubahan mendadak di lapangan).
     */
    public function update(Request $request)
    {
        $this->authorizePiket();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:'.implode(',', StatusKehadiranGuru::STATUSES),
            'keterangan' => 'nullable|string|max:1000',
        ]);

        // Guard: cegah self-status-override — user tidak boleh mengubah status
        // kehadiran dirinya sendiri (atau target yang sedang disimulasikan).
        if (in_array((int) $validated['user_id'], $this->activeUserIds(), true)) {
            return back()->with('error', 'Anda tidak diperbolehkan mengubah status kehadiran diri sendiri.');
        }

        $guru = User::where('role', User::ROLE_GURU)->whereKey((int) $validated['user_id'])->first();
        abort_unless($guru instanceof User, 422, 'Guru tidak ditemukan.');

        $record = StatusKehadiranGuru::where('user_id', $guru->id)
            ->whereDate('tanggal', $validated['tanggal'])
            ->first();

        // Guard: data testing hanya boleh dimutasi oleh Petugas IT / QA.
        $this->authorizeTestingMutation($record);

        StatusKehadiranGuru::updateOrCreate(
            [
                'user_id' => $guru->id,
                'tanggal' => $validated['tanggal'],
            ],
            [
                'status' => $validated['status'],
                'keterangan' => trim((string) ($validated['keterangan'] ?? '')) ?: null,
                'updated_by' => Auth::id() ?? $guru->id,
            ]
        );

        $labelTanggal = now()->parse($validated['tanggal'])->translatedFormat('d F Y');

        return back()->with('success', "Status kehadiran {$guru->nama} pada {$labelTanggal} diperbarui menjadi {$validated['status']}.");
    }
}