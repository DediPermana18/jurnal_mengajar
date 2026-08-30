<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan tindak lanjut Wali Kelas atas siswa bermasalah (panggil ortu, dsb).
     */
    public function up(): void
    {
        Schema::create('catatan_siswa_bermasalah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_siswa')->constrained('siswa', 'id')->cascadeOnDelete();
            $table->foreignId('id_wali_kelas')->constrained('users', 'id')->cascadeOnDelete();
            $table->string('jenis_tindakan', 40)->default('panggil_ortu'); // panggil_ortu | catatan
            $table->text('catatan')->nullable();
            $table->string('status', 30)->default('belum'); // belum | dipanggil | selesai
            $table->timestamps();

            $table->index(['id_siswa', 'id_wali_kelas']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catatan_siswa_bermasalah');
    }
};