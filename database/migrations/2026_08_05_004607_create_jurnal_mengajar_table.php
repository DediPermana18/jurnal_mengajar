<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jurnal_mengajar')) {
            Schema::create('jurnal_mengajar', function (Blueprint $table) {
                $table->id('id_jurnal');
                $table->integer('id_jadwal');
                $table->date('tanggal');
                $table->text('materi');
                $table->text('keterangan')->nullable();
                $table->enum('status_guru', ['Hadir', 'Izin', 'Sakit', 'Tugas'])->default('Hadir');
                $table->integer('jumlah_siswa_hadir')->default(0);
                $table->boolean('is_ttd')->default(false);
                $table->string('semester', 10)->default('Ganjil');
                $table->string('tahun_ajaran', 10)->default('2026/2027');
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep existing table intact
    }
};
