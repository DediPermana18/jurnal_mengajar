<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktifkan soft delete untuk Master Jam Pelajaran.
     *
     * Sebelumnya baris jam_pelajaran dihapus permanen (hard delete). Dengan
     * kolom deleted_at, slot jam yang dihapus dapat dipulihkan (restore) atau
     * dihapus permanen (force delete) melalui Recycle Bin (Super Admin / IT).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('jam_pelajaran', 'deleted_at')) {
            Schema::table('jam_pelajaran', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
