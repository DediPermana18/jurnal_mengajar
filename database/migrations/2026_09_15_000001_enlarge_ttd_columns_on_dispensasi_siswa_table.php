<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom ttd_* diubah ke longText karena menampung string Base64
     * data URI gambar tanda tangan digital yang melebihi kapasitas text (64KB).
     *
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->longText('ttd_siswa')->nullable()->change();
            $table->longText('ttd_guru')->nullable()->change();
            $table->longText('ttd_waka')->nullable()->change();
            $table->longText('ttd_pembatalan')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->text('ttd_siswa')->nullable()->change();
            $table->text('ttd_guru')->nullable()->change();
            $table->text('ttd_waka')->nullable()->change();
            $table->text('ttd_pembatalan')->nullable()->change();
        });
    }
};