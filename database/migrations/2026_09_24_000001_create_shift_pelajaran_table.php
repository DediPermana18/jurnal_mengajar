<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel master jenis shift sekolah (Shift 1 Pagi, Shift 2 Siang, dst.),
     * dikelola langsung dari halaman Master Jam Pelajaran.
     */
    public function up(): void
    {
        Schema::create('shift_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama_shift', 120);
            $table->string('keterangan', 255)->nullable();
            $table->time('jam_mulai')->nullable()->comment('Rentang Jam Utama: mulai');
            $table->time('jam_selesai')->nullable()->comment('Rentang Jam Utama: selesai');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_testing_data')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_pelajaran');
    }
};