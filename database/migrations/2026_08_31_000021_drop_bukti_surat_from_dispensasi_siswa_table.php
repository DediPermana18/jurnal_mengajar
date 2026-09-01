<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hapus fitur 'Bukti Surat / Foto Bukti' dari modul Dispensasi:
     * kolom bukti_surat (juga path file tersimpan di storage) tidak lagi dipakai.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropColumn('bukti_surat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->text('bukti_surat')->nullable()->after('ttd_siswa');
        });
    }
};