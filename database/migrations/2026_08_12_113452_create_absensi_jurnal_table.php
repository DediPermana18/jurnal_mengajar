<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('absensi_jurnal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jurnal')->constrained('jurnal')->cascadeOnDelete();
            $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Dispen'])->default('Hadir');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_jurnal', 'id_siswa']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('absensi_jurnal');
    }
};