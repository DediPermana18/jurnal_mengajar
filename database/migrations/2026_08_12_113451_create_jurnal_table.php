<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jurnal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jadwal')->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('materi');
            $table->text('catatan_kejadian')->nullable();
            $table->string('foto_kegiatan')->nullable();
            $table->timestamp('waktu_isi')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('jurnal');
    }
};