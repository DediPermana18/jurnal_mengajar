<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nisn', 20)->nullable()->unique();
            $table->string('nis', 20)->nullable()->unique();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->foreignId('id_kelas')->constrained('kelas')->cascadeOnDelete();
            $table->enum('status_siswa', ['Aktif', 'Lulus', 'Pindah', 'Keluar'])->default('Aktif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('siswa');
    }
};