<?php

namespace App\Providers;

use App\Listeners\RecordSecurityLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
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
        // Audit jejak digital login (immutable): setiap login BERHASIL
        // dicatat ke security_logs + pemilik akun diberi notifikasi bot.
        // Registrasi dijaga agar listener terdaftar hanya SATU kali (di
        // lingkungan CLI / test, provider dapat ikut di-boot lebih dari sekali).
        // ================================================================
        if (! Event::hasListeners(Login::class)) {
            Event::listen(Login::class, RecordSecurityLogin::class);
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

            // Super Admin (role 'super_admin' / sub_role 'super_admin'): akses
            // penuh ke seluruh Gate/Policy — global bypass tanpa terkecuali.
            if ($user->isSuperAdmin()) {
                return true;
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
