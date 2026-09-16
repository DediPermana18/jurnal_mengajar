<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom audit verifikasi "Izinkan Masuk Kelas" oleh Guru Mapel:
     * kapan & oleh siapa siswa diizinkan masuk kelas dari surat dispensasi telat.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->timestamp('masuk_kelas_at')->nullable()->after('mangkir_at');
            $table->foreignId('masuk_kelas_by')->nullable()->after('masuk_kelas_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('masuk_kelas_by');
            $table->dropColumn('masuk_kelas_at');
        });
    }
};