<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap Slot Jam Pelajaran (Global maupun per-Shift) kini dapat diikat ke
     * Tahun Ajaran & Semester (tahun_ajaran_id -> tabel tahun_ajaran).
     *
     * - NULL = slot "legacy" (era sebelum fitur Tahun Ajaran). Slot legacy
     *   diperlakukan sebagai kepunyaan Tahun Ajaran AKTIF (fallback aman),
     *   sehingga data berjalan yang sudah ada tetap tampil & dipakai.
     * - Bila Tahun Ajaran diganti (arsip), slot lama tidak tertimpa karena
     *   setiap operasi kelola Master Jam selalu berjalan dalam satu konteks TA.
     */
    public function up(): void
    {
        if (Schema::hasColumn('jam_pelajaran', 'tahun_ajaran_id')) {
            return;
        }

        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('jenis');
            $table->foreign('tahun_ajaran_id')
                ->references('id')->on('tahun_ajaran')
                ->nullOnDelete();
            $table->index('tahun_ajaran_id', 'idx_jam_pelajaran_tahun_ajaran_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('jam_pelajaran', 'tahun_ajaran_id')) {
            return;
        }

        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropIndex('idx_jam_pelajaran_tahun_ajaran_id');
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropColumn('tahun_ajaran_id');
        });
    }
};