<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah nilai enum "Terlambat (T)" pada status presensi jurnal mengajar.
     * Digunakan saat Guru Mapel memverifikasi surat dispensasi masuk kelas
     * (siswa telat) dan siswa diizinkan masuk kelas pada JP yang bersangkutan.
     */
    public function up(): void
    {
        Schema::table('absensi_jurnal', function (Blueprint $table) {
            $table->enum('status', ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Dispen', 'Terlambat'])->default('Hadir')->change();
        });
    }

    public function down(): void
    {
        Schema::table('absensi_jurnal', function (Blueprint $table) {
            $table->enum('status', ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Dispen'])->default('Hadir')->change();
        });
    }
};