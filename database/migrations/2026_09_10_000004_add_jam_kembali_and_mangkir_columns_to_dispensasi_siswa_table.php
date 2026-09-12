<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk Fitur Part 3 (Jam Kembali):
     * 1. jam_kembali_jp & tidak_kembali_hari_ini -> rencana siswa kembali ke sekolah.
     * 2. kembali_at / kembali_by -> konfirmasi siswa kembali oleh Satpam.
     * 3. mangkir_at -> jejak saat dispensasi dinyatakan Mangkir / Bolos (auto-alfa).
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->unsignedInteger('jam_kembali_jp')->nullable()->after('jam_masuk_jp');
            $table->boolean('tidak_kembali_hari_ini')->default(false)->after('jam_kembali_jp');
            $table->timestamp('kembali_at')->nullable()->after('dibatalkan_at');
            $table->foreignId('kembali_by')
                ->nullable()
                ->after('kembali_at')
                ->constrained('users', 'id')
                ->nullOnDelete();
            $table->timestamp('mangkir_at')->nullable()->after('kembali_by');
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kembali_by');
            $table->dropColumn([
                'jam_kembali_jp',
                'tidak_kembali_hari_ini',
                'kembali_at',
                'mangkir_at',
            ]);
        });
    }
};
