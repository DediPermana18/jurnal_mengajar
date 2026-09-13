<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * Global Scope is_testing_data: isolasi data berdasarkan peran pengguna.
 *
 * - Petugas IT / QA Tester (isTestingUser() TRUE — termasuk saat impersonasi
 *   "Switch View As"): hanya melihat data testing (is_testing_data = true).
 * - User non-IT (admin, kurikulum, guru, siswa) & guest: hanya melihat data
 *   real (is_testing_data = false).
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
        } finally {
            static::$resolving = false;
        }

        // Petugas IT / QA Tester: dipaksa hanya melihat data testing.
        if ($user instanceof User && $user->isTestingUser()) {
            if ($model instanceof User) {
                // Untuk model User, izinkan ID user tester sendiri agar session Auth tetap valid
                $builder->where(function ($q) use ($table, $user) {
                    $q->where("{$table}.is_testing_data", true)
                        ->orWhere("{$table}.id", $user->id);
                });
            } else {
                $builder->where("{$table}.is_testing_data", true);
            }
        }
        // User non-IT & guest: hanya melihat data real.
        else {
            $builder->where("{$table}.is_testing_data", false);
        }
    }
}
