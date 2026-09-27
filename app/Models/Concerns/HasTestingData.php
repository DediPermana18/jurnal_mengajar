<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TestingDataScope;
use App\Models\User;

/**
 * Trait penanda data testing (sandbox) pada model transaksional:
 *
 * 1. Menambahkan Global Scope TestingDataScope — isolasi data yang konsisten:
 *    - Model berpenanda TestingDataContextAware (sub-sistem penjadwalan:
 *      Shift, Slot Jam, Jam Pulang, Plotting/Jadwal)
 *      mengikuti KONTEKS LINGKUNGAN AKTIF (User::currentTestingStatus()):
 *      Tahun Ajaran aktif is_testing_data=1 ATAU user Petugas IT/QA/test,
 *      sehingga data yang baru dibuat dalam mode testing langsung terlihat.
 *    - Model lain (termasuk Master Data Tahun Ajaran, Kelas, Guru, Siswa,
 *      Jurusan, Mata Pelajaran, Ruangan) mempertahankan isolasi lama berbasis
 *      peran user
 *      (Petugas IT/QA hanya testing; lainnya hanya real) — menghindari
 *      data isolation lockout ketika Tahun Ajaran testing diaktifkan.
 *
 * 2. Model event 'creating': is_testing_data di-set mengikuti konteks yang
 *    sedang berjalan — untuk model penjadwalan mengikuti lingkungan aktif
 *    (TA testing / user IT); untuk model lain hanya user IT/QA/test yang
 *    SELALU menulis ke partisi testing. Dengan ini tidak ada mismatch
 *    antara flag tulis dan filter baca di setiap modul.
 */
trait HasTestingData
{
    public static function bootHasTestingData(): void
    {
        static::addGlobalScope(new TestingDataScope);

        static::creating(function ($model) {
            if (array_key_exists('is_testing_data', $model->getAttributes())) {
                return;
            }

            $user = auth()->user();

            // Sub-sistem penjadwalan: mewarisi konteks lingkungan aktif
            // (Tahun Ajaran testing aktif ATAU user Petugas IT/QA/tester).
            if ($model instanceof TestingDataContextAware) {
                $model->is_testing_data = User::currentTestingStatus();

                return;
            }

            // Model lain: isolasi berbasis user (legacy) — hanya akun
            // Petugas IT / QA Tester / sandbox yang menulis ke partisi testing.
            if ($user instanceof User && $user->isTestingUser()) {
                $model->is_testing_data = true;
            }
        });
    }
}