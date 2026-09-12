<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TestingDataScope;
use App\Models\User;

/**
 * Trait penanda data testing (sandbox) pada model transaksional:
 *
 * 1. Menambahkan Global Scope TestingDataScope — pada pendekatan ini SEMUA
 *    user melihat seluruh data (real + testing); data testing ditandai badge
 *    "[TESTING]" dan tidak dapat diubah/dihapus oleh user non-IT (dijaga di
 *    lapisan controller). Petugas IT / QA Tester dapat memfilter via sesi.
 *
 * 2. Model event 'creating': bila yang menginput adalah Petugas IT / QA Tester
 *    (langsung atau saat impersonation), is_testing otomatis di-set true
 *    sehingga data sandbox tidak tercampur dengan data real.
 */
trait HasTestingData
{
    public static function bootHasTestingData(): void
    {
        static::addGlobalScope(new TestingDataScope);

        static::creating(function ($model) {
            if (array_key_exists('is_testing', $model->getAttributes())) {
                return;
            }

            $user = auth()->user();
            if ($user instanceof User && $user->isTestingUser()) {
                $model->is_testing = true;
            }
        });
    }
}
