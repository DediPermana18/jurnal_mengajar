<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integrasi Catatan Terlambat (input Satpam) dengan Dispensasi Masuk Kelas
     * yang diterbitkan Guru Piket:
     * - is_approved_piket : true bila Guru Piket telah mengonfirmasi siswa
     *   terlambat ini lewat surat Dispen Masuk Kelas.
     * - dispensasi_id     : FK ke DispensasiSiswa yang menjadi surat izin masuk
     *   kelas (memungkinkan surat menampilkan jam kedatangan di gerbang).
     */
    public function up(): void
    {
        Schema::table('catatan_terlambat', function (Blueprint $table) {
            $table->boolean('is_approved_piket')->default(false)->after('keterangan');
            $table->foreignId('dispensasi_id')
                ->nullable()
                ->after('id_satpam')
                ->constrained('dispensasi_siswa', 'id')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catatan_terlambat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispensasi_id');
            $table->dropColumn('is_approved_piket');
        });
    }
};
