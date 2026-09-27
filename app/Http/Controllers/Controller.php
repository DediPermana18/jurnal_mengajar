<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Apakah user yang login berhak atas area Data Master / Admin TU?
     *
     * Urutan prioritas:
     *  1. Petugas IT / QA Tester (langsung maupun sedang impersonation "Switch View As")
     *     selalu diizinkan sebagai penguji — inputan dibedakan lewat flag is_testing_data.
     *  2. Role efektif (effectiveRole dari active_role) dicek terlebih dahulu,
     *     sehingga impersonasi admin_tu / waka_* tetap mendapat akses sesuai
     *     role yang dipilih tanpa perlu login ulang.
     *  3. Role asli admin / admin_tu / super_admin tetap berlaku seperti semula.
     */
    protected function isAuthorizedAdminArea(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isPetugasIt()) {
            return true;
        }

        return in_array($user->effectiveRole(), ['admin'], true)
            || in_array($user->role, ['admin', 'admin_tu', 'super_admin'], true);
    }

    /**
     * Apakah user yang login adalah user operasional biasa (bukan penguji)?
     *
     * Sesuai kebijakan isolasi data testing pada area Data Master: user
     * operasional biasa HANYA boleh melihat data PRODUKSI (is_testing_data = 0),
     * apa pun konteks lingkungan aktif (mis. Tahun Ajaran testing yang sedang
     * diaktifkan oleh IT). Predikat ini bermakna BUKAN Petugas IT / QA Tester /
     * akun sandbox (isTestingUser) DAN BUKAN Super Admin — pola yang sama dengan
     * contoh kebijakan `!is_tester && !is_super_admin`.
     *
     * Penguji & Super Admin tidak dilewati filter ini; jangkauan partisinya
     * tetap diatur sepenuhnya oleh global scope TestingDataScope.
     */
    protected function isRegularOperationalUser(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isTestingUser()) {
            return false;
        }

        return ! $user->isSuperAdmin();
    }

    /**
     * Guard mutasi data testing (is_testing_data = true).
     *
     * Hanya Petugas IT / QA Tester yang boleh mengubah, menghapus, menyetujui,
     * atau mengubah status record yang di-flag sebagai data pengujian.
     * User biasa (termasuk guest via link publik) ditolak dengan 403.
     */
    protected function authorizeTestingMutation(?Model $model): void
    {
        if (! $model) {
            return;
        }

        if (! $model->is_testing_data) {
            return;
        }

        $user = Auth::user();

        if (! $user instanceof User || ! $user->isPetugasIt()) {
            abort(403, 'Aksi ditolak. Data pengujian hanya dapat dikelola oleh IT.');
        }
    }

    /**
     * Guard batch mutasi data testing: tolak bila ada record is_testing_data = true
     * yang akan terpengaruh operasi ini dan pelakunya BUKAN Petugas IT / QA.
     */
    protected function authorizeTestingBatch(?Builder $query): void
    {
        if (! $query) {
            return;
        }

        $user = Auth::user();

        if ($user instanceof User && $user->isPetugasIt()) {
            return;
        }

        if ($query->exists()) {
            abort(403, 'Aksi ditolak. Data pengujian hanya dapat dikelola oleh IT.');
        }
    }
}
