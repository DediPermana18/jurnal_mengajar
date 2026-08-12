<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_id');
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);
            $table->foreignId('id_jam')->constrained('jam_pelajaran')->cascadeOnDelete();
            $table->foreignId('id_kelas')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('id_mapel')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('id_guru')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_tahun_ajaran')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_kelas', 'hari', 'id_jam', 'id_tahun_ajaran'], 'unq_kelas_hari_jam');
            $table->unique(['id_guru', 'hari', 'id_jam', 'id_tahun_ajaran'], 'unq_guru_hari_jam');
        });
    }

    public function down(): void {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};