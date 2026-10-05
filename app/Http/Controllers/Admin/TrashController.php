<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Scopes\ActiveTahunAjaranScope;
use App\Models\ShiftPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Recycle Bin ("Data Terhapus") — khusus Super Admin / Petugas IT.
 *
 * Menampilkan record soft-deleted (query onlyTrashed()) per kategori dan
 * menyediakan dua aksi:
 *  - restore()     : pulihkan record (menghapus penanda deleted_at).
 *  - forceDelete() : hapus PERMANEN dari database — tidak dapat dikembalikan.
 *
 * Isolasi is_testing_data dipertahankan lewat global scope TestingDataScope:
 *  - Super Admin   : mengelola hanya data partisi PRODUKSI (is_testing_data=0).
 *  - Petugas IT/QA : hanya partisi TESTING (is_testing_data=1).
 * Untuk JamPelajaran, scope Tahun Ajaran aktif di-bypass (sama seperti Master
 * Jam Pelajaran di sisi admin) agar seluruh slot arsip/TA lama yang terhapus
 * tetap tampil di Recycle Bin.
 */
class TrashController extends Controller
{
    /**
     * Daftar kategori yang dikelola — kunci dipakai pada route
     * /admin/trash/{model}/{id}/... (whitelist: tidak ada akses model sembarangan).
     *
     * 'disableActiveTAScope' => pidahkan scope Tahun Ajaran aktif untuk kategori
     * yang scopenya menyembunyikan data arsip (JamPelajaran).
     */
    protected const MODELS = [
        'guru' => [
            'label' => 'Guru',
            'icon' => 'bi-person-vcard',
            'disableActiveTAScope' => false,
        ],
        'siswa' => [
            'label' => 'Siswa',
            'icon' => 'bi-people',
            'disableActiveTAScope' => false,
        ],
        'kelas' => [
            'label' => 'Kelas',
            'icon' => 'bi-door-open',
            'disableActiveTAScope' => false,
        ],
        'user' => [
            'label' => 'User / Pengguna',
            'icon' => 'bi-person-gear',
            'disableActiveTAScope' => false,
        ],
        'jam-pelajaran' => [
            'label' => 'Jam Pelajaran',
            'icon' => 'bi-clock-history',
            'disableActiveTAScope' => true,
        ],
        'jadwal-pelajaran' => [
            'label' => 'Jadwal Pelajaran',
            'icon' => 'bi-calendar2-x',
            'disableActiveTAScope' => false,
        ],
        'mata-pelajaran' => [
            'label' => 'Mata Pelajaran',
            'icon' => 'bi-book',
            'disableActiveTAScope' => false,
        ],
        'jurusan' => [
            'label' => 'Jurusan',
            'icon' => 'bi-diagram-3',
            'disableActiveTAScope' => false,
        ],
        'tahun-ajaran' => [
            'label' => 'Tahun Ajaran',
            'icon' => 'bi-calendar3',
            'disableActiveTAScope' => false,
        ],
    ];

    /**
     * Label human-readable untuk role / sub_role user (kategori 'user').
     */
    protected const ROLE_LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'admin_tu' => 'Admin TU',
        'admin_kurikulum' => 'Admin Kurikulum',
        'admin_kesiswaan' => 'Admin Kesiswaan',
        'petugas_it' => 'Petugas IT',
        'qa_tester' => 'QA Tester',
        'guru' => 'Guru',
        'guru_mapel' => 'Guru Mapel',
        'wali_kelas' => 'Wali Kelas',
        'guru_piket' => 'Guru Piket',
        'piket_satpam' => 'Piket Satpam',
        'satpam' => 'Satpam',
        'kepsek' => 'Kepala Sekolah',
        'kepala_sekolah' => 'Kepala Sekolah',
        'petugas_tu' => 'Petugas TU',
        'waka_kurikulum' => 'Waka Kurikulum',
        'waka_kesiswaan' => 'Waka Kesiswaan',
        'waka_sdm' => 'Waka SDM',
        'waka_piket' => 'Waka Piket',
        'kurikulum' => 'Kurikulum',
        'kesiswaan' => 'Kesiswaan',
        'sdm' => 'SDM',
        'piket' => 'Koordinator Piket',
        'koordinator_piket' => 'Koordinator Piket',
    ];

    /**
     * Otorisasi: hanya Super Admin / Petugas IT (termasuk akun legasi 'admin'
     * tanpa sub_role — konsep Super Admin awal aplikasi) yang boleh masuk.
     */
    protected function authorizeAccess(): void
    {
        $user = auth()->user();
        $isAllowed = $user && (
            $user->isPetugasIt()
            || $user->isSuperAdmin()
            || ($user->role === User::ROLE_ADMIN && $user->sub_role === null)
        );

        abort_unless($isAllowed, 403, 'Akses ditolak: hanya Super Admin / Petugas IT yang dapat mengelola Data Terhapus.');
    }

    /**
     * Halaman Recycle Bin: daftar record soft-deleted per kategori + jumlah.
     */
    public function index(): View
    {
        $this->authorizeAccess();

        $categories = [];

        foreach (static::MODELS as $key => $cfg) {
            $records = $this->categoryQuery($key, $cfg)->get();

            $categories[$key] = [
                'key' => $key,
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'total' => $records->count(),
                'ids' => $records
                    ->map(fn (Model $record) => (string) $record->getKey())
                    ->values()
                    ->all(),
                'rows' => $records
                    ->sortByDesc('deleted_at')
                    ->map(fn (Model $record) => $this->row($key, $cfg['label'], $record))
                    ->values()
                    ->all(),
            ];
        }

        $totalTrash = collect($categories)->sum('total');

        return view('admin.trash.index', compact('categories', 'totalTrash'));
    }

    /**
     * Pulihkan record yang terhapus (restore).
     */
    public function restore(Request $request, string $model, int $id): RedirectResponse
    {
        $this->authorizeAccess();
        $this->abortIfUnknownModel($model);

        $record = $this->trashedRecord($model, $id);
        [$title] = $this->describe($model, $record);
        $label = static::MODELS[$model]['label'];

        try {
            DB::transaction(function () use ($record) {
                $record->restore();
            });
        } catch (\Throwable $e) {
            // Contoh gagal: ada record BARU yang sudah memakai nilai unik yang sama
            // (username/NISN/kode), sehingga restore memicu pelanggaran unique index.
            Log::warning('security:trash-restore-failed', [
                'model_key' => $model,
                'record_id' => $id,
                'actor_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Gagal memulihkan data {$label}: {$e->getMessage()}");
        }

        Log::info('security:trash-restore', [
            'model_key' => $model,
            'record_id' => $id,
            'title' => $title,
            'actor_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', "Data {$label} \"{$title}\" berhasil dipulihkan (restore).");
    }

    /**
     * Hapus permanen record dari database (force delete) — tidak dapat dikembalikan.
     */
    public function forceDelete(Request $request, string $model, int $id): RedirectResponse
    {
        $this->authorizeAccess();
        $this->abortIfUnknownModel($model);

        $record = $this->trashedRecord($model, $id);
        [$title] = $this->describe($model, $record);
        $label = static::MODELS[$model]['label'];

        try {
            DB::transaction(function () use ($record) {
                $record->forceDelete();
            });
        } catch (\Throwable $e) {
            Log::warning('security:trash-force-delete-failed', [
                'model_key' => $model,
                'record_id' => $id,
                'actor_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Gagal menghapus permanen data {$label}: {$e->getMessage()}");
        }

        Log::info('security:trash-force-delete', [
            'model_key' => $model,
            'record_id' => $id,
            'title' => $title,
            'actor_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', "Data {$label} \"{$title}\" telah dihapus PERMANEN dari database dan tidak dapat dikembalikan.");
    }

    /**
     * Pulihkan massal (bulk restore) beberapa record terhapus.
     *
     * Hanya record yang benar-benar trashed dan berada di partisi aktor
     * (isolasi is_testing_data via global scope) yang ikut diproses —
     * id di luar partisi hanya diabaikan oleh query onlyTrashed().
     */
    public function restoreBulk(Request $request, string $model): RedirectResponse
    {
        $this->authorizeAccess();
        $this->abortIfUnknownModel($model);

        $label = static::MODELS[$model]['label'];
        $ids = $this->normalizeIds($request->input('ids'));

        if ($ids === []) {
            return back()->with('error', "Pilih minimal satu data {$label} yang akan diproses.");
        }

        try {
            $count = DB::transaction(function () use ($model, $ids) {
                return $this->trashBulkQuery($model)
                    ->whereIn('id', $ids)
                    ->restore();
            });
        } catch (\Throwable $e) {
            Log::warning('security:trash-restore-bulk-failed', [
                'model_key' => $model,
                'ids' => $ids,
                'actor_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Gagal memulihkan data {$label} massal: {$e->getMessage()}");
        }

        Log::info('security:trash-restore-bulk', [
            'model_key' => $model,
            'ids' => $ids,
            'count' => $count,
            'actor_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', "{$count} data {$label} berhasil dipulihkan (restore).");
    }

    /**
     * Hapus permanen massal (bulk force delete) — tidak dapat dikembalikan.
     */
    public function forceDeleteBulk(Request $request, string $model): RedirectResponse
    {
        $this->authorizeAccess();
        $this->abortIfUnknownModel($model);

        $label = static::MODELS[$model]['label'];
        $ids = $this->normalizeIds($request->input('ids'));

        if ($ids === []) {
            return back()->with('error', "Pilih minimal satu data {$label} yang akan diproses.");
        }

        try {
            $count = DB::transaction(function () use ($model, $ids) {
                return $this->trashBulkQuery($model)
                    ->whereIn('id', $ids)
                    ->forceDelete();
            });
        } catch (\Throwable $e) {
            Log::warning('security:trash-force-delete-bulk-failed', [
                'model_key' => $model,
                'ids' => $ids,
                'actor_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', "Gagal menghapus permanen data {$label} massal: {$e->getMessage()}");
        }

        Log::info('security:trash-force-delete-bulk', [
            'model_key' => $model,
            'ids' => $ids,
            'count' => $count,
            'actor_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', "{$count} data {$label} telah dihapus PERMANEN dari database dan tidak dapat dikembalikan.");
    }

    // ------------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------------

    protected function modelClass(string $key): string
    {
        return [
            'guru' => Guru::class,
            'siswa' => Siswa::class,
            'kelas' => Kelas::class,
            'user' => User::class,
            'jam-pelajaran' => JamPelajaran::class,
            'jadwal-pelajaran' => JadwalPelajaran::class,
            'mata-pelajaran' => MataPelajaran::class,
            'jurusan' => Jurusan::class,
            'tahun-ajaran' => TahunAjaran::class,
        ][$key];
    }

    protected function abortIfUnknownModel(string $key): void
    {
        abort_unless(isset(static::MODELS[$key]), 404, 'Kategori data tidak dikenal.');
    }

    /**
     * Query record terhapus untuk satu kategori (onlyTrashed), dengan isolasi
     * is_testing_data otomatis via global scope.
     */
    protected function categoryQuery(string $key, array $cfg): Builder
    {
        $model = $this->modelClass($key);
        $query = $model::onlyTrashed();

        if (($cfg['disableActiveTAScope'] ?? false)) {
            $query->withoutGlobalScope(ActiveTahunAjaranScope::class);
        }

        return $query->orderBy('deleted_at', 'desc');
    }

    /**
     * Cari satu record terhapus berdasarkan model + id. Memakai none-disable
     * scope sesuai kategori (lihat MODELS) dan memastikan record memang trashed.
     */
    protected function trashedRecord(string $model, int $id): Model
    {
        $class = $this->modelClass($model);
        $query = $class::withTrashed();

        if ((static::MODELS[$model]['disableActiveTAScope'] ?? false)) {
            $query->withoutGlobalScope(ActiveTahunAjaranScope::class);
        }

        $record = $query->find($id);
        abort_unless($record && $record->trashed(), 404, 'Record tidak ditemukan pada Recycle Bin.');

        return $record;
    }

    /**
     * Query trashed khusus aksi massal — menerapkan disableActiveTAScope
     * sesuai kategori (sama seperti categoryQuery) agar jam pelajaran arsip
     * tetap terproses dan isolasi is_testing_data tetap terjaga.
     */
    protected function trashBulkQuery(string $key): Builder
    {
        $query = $this->modelClass($key)::onlyTrashed();

        if ((static::MODELS[$key]['disableActiveTAScope'] ?? false)) {
            $query->withoutGlobalScope(ActiveTahunAjaranScope::class);
        }

        return $query;
    }

    /**
     * Normalisasi array id yang dikirim form checkbox (ids[]) menjadi array
     * int unik — mengabaikan nilai kosong/non-numerik.
     */
    protected function normalizeIds(mixed $input): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $input))));
    }

    /**
     * Bentuk baris generik untuk view.
     */
    protected function row(string $key, string $label, Model $record): array
    {
        [$title, $subtitle] = $this->describe($key, $record);

        return [
            'key' => $key,
            'label' => $label,
            'id' => $record->getKey(),
            'title' => $title,
            'subtitle' => $subtitle,
            'deleted_at' => $record->deleted_at,
            'restoreUrl' => route('admin.trash.restore', [$key, $record->getKey()]),
            'forceUrl' => route('admin.trash.force-delete', [$key, $record->getKey()]),
        ] + ($key === 'user' ? [
            'email' => $record->email,
            'role_label' => $this->roleLabel($record),
        ] : []);
    }

    /**
     * Label role / akses user (kategori 'user'), mis. "Admin · Petugas TU".
     */
    protected function roleLabel(User $record): string
    {
        $role = static::ROLE_LABELS[$record->role] ?? $this->humanize($record->role);

        if ($record->sub_role && $record->sub_role !== $record->role) {
            $sub = static::ROLE_LABELS[$record->sub_role] ?? $this->humanize($record->sub_role);
            $role .= ' · '.$sub;
        }

        return $role;
    }

    protected function humanize(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    /**
     * Deskripsi ringkas [judul, subjudul] per kategori — resolusi relasi best-effort.
     */
    protected function describe(string $key, Model $record): array
    {
        switch ($key) {
            case 'guru':
                return [
                    $record->nama,
                    '@'.$record->username.($record->nip ? ' · NIP: '.$record->nip : ''),
                ];

            case 'siswa':
                $kelas = $record->id_kelas ? Kelas::withTrashed()->find($record->id_kelas) : null;

                return [
                    $record->nama,
                    trim(
                        ($record->nis ? 'NIS: '.$record->nis : '')
                        .($record->nisn ? ' · NISN: '.$record->nisn : '')
                        .' · Kelas: '.($kelas ? $kelas->nama_kelas : '-')
                    ),
                ];

            case 'kelas':
                $jurusan = $record->id_jurusan ? Jurusan::withTrashed()->find($record->id_jurusan) : null;

                return [
                    $record->nama_kelas,
                    'Tingkat '.$record->tingkat.($jurusan ? ' · '.$jurusan->nama_jurusan : ''),
                ];

            case 'user':
                return [
                    $record->nama,
                    '@'.$record->username.' · ID: '.$record->getKey(),
                ];

            case 'jam-pelajaran':
                $shift = $record->shift_id
                    ? ShiftPelajaran::find($record->shift_id)
                    : null;

                return [
                    'Jam ke-'.$record->jam_ke.' · '.$this->cleanTime($record->jam_mulai).'–'.$this->cleanTime($record->jam_selesai),
                    $record->hari.' · '.$record->kategori_hari.($shift ? ' · '.$shift->nama_shift : ''),
                ];

            case 'jadwal-pelajaran':
                $kelas = $record->id_kelas ? Kelas::withTrashed()->find($record->id_kelas) : null;
                $mapel = $record->id_mapel ? MataPelajaran::withTrashed()->find($record->id_mapel) : null;
                $guru = $record->id_guru ? User::withTrashed()->find($record->id_guru) : null;
                $jam = $record->id_jam
                    ? JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                        ->withTrashed()
                        ->find($record->id_jam)
                    : null;

                return [
                    $record->hari.' · '.($kelas ? $kelas->nama_kelas : 'Kelas -').' — '.($mapel ? $mapel->nama_mapel : 'Mapel -'),
                    ($guru ? $guru->nama : 'Guru -')
                        .($jam ? ' · '.$this->cleanTime($jam->jam_mulai).'–'.$this->cleanTime($jam->jam_selesai) : ''),
                ];

            case 'mata-pelajaran':
                return [
                    $record->nama_mapel,
                    $record->kode_mapel ? 'Kode: '.$record->kode_mapel : '',
                ];

            case 'jurusan':
                return [
                    $record->nama_jurusan,
                    $record->kode_jurusan ? 'Kode: '.$record->kode_jurusan : '',
                ];

            case 'tahun-ajaran':
                return [
                    $record->tahun_ajaran.' · '.$record->semester,
                    $record->is_active ? 'Status: Aktif' : 'Status: Arsip',
                ];

            default:
                return [get_class($record), '#'.$record->getKey()];
        }
    }

    /**
     * Normalisasi waktu "08:00:00" → "08:00".
     */
    protected function cleanTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }
}
