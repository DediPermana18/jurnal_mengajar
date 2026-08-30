<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hubungan Catatan Terlambat -> Penerima (Guru Piket bertugas hari itu
     * dan Wali Kelas siswa). Tabel pivot yang "mengirim/menghubungkan"
     * record keterlambatan ke semua penerima terkait.
     */
    public function up(): void
    {
        Schema::create('penerima_catatan_terlambat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catatan_terlambat_id')->constrained('catatan_terlambat', 'id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->string('peran', 20); // guru_piket | wali_kelas
            $table->timestamps();

            $table->unique(['catatan_terlambat_id', 'user_id', 'peran'], 'pct_terlambat_user_peran_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penerima_catatan_terlambat');
    }
};