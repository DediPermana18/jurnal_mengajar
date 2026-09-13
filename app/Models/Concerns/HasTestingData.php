<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TestingDataScope;
use App\Models\User;

/**
 * Trait penanda data testing (sandbox) pada model transaksional:
 *
 * 1. Menambahkan Global Scope TestingDataScope — isolasi data secara ketat:
 *    - Petugas IT / QA Tester (isTestingUser() TRUE, termasuk saat impersonasi)
 *      hanya melihat data testing (is_testing_data = true).
 *    - User non-IT / guest hanya melihat data real (is_testing_data = false).
 *
 * 2. Model event 'creating': bila yang menginput adalah Petugas IT / QA Tester
 *    (langsung atau saat impersonation), is_testing_data otomatis di-set true
 *    sehingga data sandbox tidak tercampur dengan data real.
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
            if ($user instanceof User && $user->isTestingUser()) {
                $model->is_testing_data = true;
            }
        });
    }
}
