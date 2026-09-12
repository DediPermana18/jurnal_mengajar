<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Scope is_testing (pendekatan baru: tampil-tapi-terkunci / read-only):
 *
 * - Semua user (termasuk non-IT & guest) MELIHAT seluruh data, baik real
 *   maupun testing. Data testing ditandai badge "[TESTING]" di antarmuka dan
 *   tidak dapat diubah/dihapus oleh user non-IT (dijaga di lapisan controller).
 * - Petugas IT / QA Tester tetap dapat memfilter data via sesi 'testing_view':
 *     'all'     -> semua data (real + testing) [default]
 *     'real'    -> hanya is_testing = false
 *     'testing' -> hanya is_testing = true
 */
class TestingDataScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // Non-IT dan guest: jangan filter apa pun — semua data tampil.
        if (! $user instanceof User || ! $user->isPetugasIt()) {
            return;
        }

        // IT / QA: filter opsional sesuai mode sesi.
        $column = $model->qualifyColumn('is_testing');
        $mode = User::testingViewMode();

        if ($mode === 'real') {
            $builder->where($column, false);
        } elseif ($mode === 'testing') {
            $builder->where($column, true);
        }
    }
}
