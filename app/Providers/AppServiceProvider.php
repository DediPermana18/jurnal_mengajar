<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        if (request()->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            URL::forceScheme('https');
        }

        // ================================================================
        // Gate::before — bypass seluruh pengecekan Gate/Policy untuk:
        //   1. Petugas IT / QA Tester (role asli petugas_it / qa_tester)
        //      — termasuk saat dalam mode impersonasi "Switch View As".
        //   2. Tester yang sedang aktif impersonasi role lain (hasActiveRole)
        //      — memastikan aksi seperti approve dispensasi, edit data, dll
        //      tidak diblokir meski role efektif bukan admin.
        // Non-IT tanpa impersonasi mengembalikan null agar evaluasi Gate normal.
        // ================================================================
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            // Petugas IT asli (role DB = petugas_it / qa_tester)
            if ($user->isPetugasIt()) {
                return true;
            }

            // User yang sedang aktif impersonasi (Switch View As)
            // Ini mencakup kasus Tester impersonasi Waka Kesiswaan, Guru, dll.
            if ($user->hasActiveRole()) {
                return true;
            }

            return null;
        });
    }
}
