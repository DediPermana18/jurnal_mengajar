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

        // Bypass seluruh pengecekan Gate/Policy untuk Petugas IT / QA Tester dan
        // mode impersonation ("Switch View As" -> session active_role).
        // Non-IT mengembalikan null agar evaluasi Gate berjalan normal.
        Gate::before(function ($user, string $ability) {
            if ($user instanceof User && $user->isPetugasIt()) {
                return true;
            }

            return null;
        });
    }
}
