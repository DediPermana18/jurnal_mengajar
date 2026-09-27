<?php

namespace App\Models\Scopes;

use App\Models\Concerns\TestingDataContextAware;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * Global Scope is_testing_data — isolasi data secara konsisten per KONTEKS.
 *
 * - Model berpenanda TestingDataContextAware (sub-sistem penjadwalan: Shift,
 *   Slot Jam, Jam Pulang, Plotting/Jadwal) mengikuti
 *   KONTEKS LINGKUNGAN AKTIF (User::currentTestingStatus()): Tahun Ajaran aktif
 *   ber-is_testing_data=1 ATAU user Petugas IT / QA Tester (termasuk saat
 *   impersonasi "Switch View As" & akun sandbox is_testing_data=1).
 *   Konteks TESTING (TRUE) → hanya partisi testing; PRODUKSI (FALSE) → real.
 * - Model lain (User, Tahun Ajaran, Kelas, Siswa, Jurusan, Mata Pelajaran,
 *   Ruangan, Jurnal, presensi, izin, dsb.) mempertahankan isolasi lama
 *   berbasis peran: Petugas IT/QA hanya melihat testing, lainnya hanya real —
 *   sehingga Tahun Ajaran testing yang aktif tidak mengunci data real dan
 *   user operasional biasa selalu melihat hanya data PRODUKSI pada Data Master.
 */
class TestingDataScope implements Scope
{
    /**
     * Cache hasil pengecekan kolom is_testing_data per nama tabel.
     */
    protected static array $hasColumnCache = [];

    /**
     * Re-entrancy guard: mencegah infinite loop saat auth()->user()
     * memicu query ke model User yang kembali memanggil scope ini.
     */
    protected static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();
        if (! isset(static::$hasColumnCache[$table])) {
            static::$hasColumnCache[$table] = Schema::hasColumn($table, 'is_testing_data');
        }

        // Tabel tanpa kolom is_testing_data tidak difilter di scope ini.
        if (! static::$hasColumnCache[$table]) {
            return;
        }

        // Jika scope sedang dalam proses resolve auth()->user() (re-entrant call),
        // terapkan filter aman (non-testing) tanpa memanggil auth() lagi
        // untuk mencegah infinite loop / stack overflow.
        if (static::$resolving) {
            $builder->where("{$table}.is_testing_data", false);

            return;
        }

        static::$resolving = true;
        try {
            $user = auth()->user();
            $contextAware = $model instanceof TestingDataContextAware;
            // Model penjadwalan: ikuti konteks lingkungan aktif (TA testing / user IT).
            // Model lain: isolasi lama berbasis peran user (hindari lockout).
            $testing = $contextAware
                ? User::currentTestingStatus()
                : ($user instanceof User && $user->isTestingUser());
        } finally {
            static::$resolving = false;
        }

        if ($testing) {
            if ($model instanceof User) {
                // Untuk model User, izinkan ID user sendiri agar session Auth tetap valid.
                $builder->where(function ($q) use ($table, $user) {
                    $q->where("{$table}.is_testing_data", true);
                    if ($user instanceof User) {
                        $q->orWhere("{$table}.id", $user->id);
                    }
                });
            } else {
                $builder->where("{$table}.is_testing_data", true);
            }
        }
        // Di luar konteks testing (produksi): hanya melihat data real.
        else {
            $builder->where("{$table}.is_testing_data", false);
        }
    }
}
