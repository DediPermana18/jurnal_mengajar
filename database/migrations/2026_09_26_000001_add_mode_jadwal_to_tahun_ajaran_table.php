<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode penjadwalan per Tahun Ajaran: 'global' (satu struktur jam untuk seluruh
     * kelas) atau 'shift' (mendukung Shift 1, Shift 2, dst.). NULL = belum ditentukan
     * (TA lama / legacy) sehingga mengikuti tipe penjadwalan sistem (app_settings.schedule_mode).
     */
    public function up(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->string('mode_jadwal')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->dropColumn('mode_jadwal');
        });
    }
};