<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom relasi id_dispensasi pada presensi jurnal mengajar.
     * Dipakai tombol "Lihat Surat" di Rekap Presensi agar Guru Mapel / Wali
     * Kelas dapat membuka surat dispensasi digital terkait (SIM-/DIS-) langsung
     * dari kolom keterangan presensi.
     */
    public function up(): void
    {
        Schema::table('absensi_jurnal', function (Blueprint $table) {
            $table->unsignedBigInteger('id_dispensasi')->nullable()->after('id_siswa');
            $table->index('id_dispensasi');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_jurnal', function (Blueprint $table) {
            $table->dropIndex(['id_dispensasi']);
            $table->dropColumn('id_dispensasi');
        });
    }
};