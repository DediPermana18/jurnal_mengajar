<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Header/induk transaksi dispensasi kolektif. Satu pengajuan untuk banyak
     * siswa (rombongan): alasan, jam pelajaran, & TTD Guru Piket dipakai
     * bersama, sedangkan masing-masing siswa menjadi baris anak di
     * tabel dispensasi_siswa (dispensasi_kolektif_id) dengan TTD-nya sendiri.
     */
    public function up(): void
    {
        Schema::create('dispensasi_kolektif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_guru_piket')->constrained('users', 'id')->cascadeOnDelete();
            $table->foreignId('id_jadwal')->nullable()->constrained('jadwal_pelajaran', 'id')->onDelete('set null');
            $table->foreignId('id_guru')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->date('tanggal');
            $table->string('tipe_dispen')->default('keluar');
            $table->string('jam_ke')->nullable();
            $table->unsignedTinyInteger('jam_keluar_jp')->nullable();
            $table->unsignedTinyInteger('jam_masuk_jp')->nullable();
            $table->unsignedTinyInteger('jam_kembali_jp')->nullable();
            $table->boolean('tidak_kembali_hari_ini')->default(true);
            $table->text('alasan');
            $table->string('status')->default('disetujui');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', 'id')->onDelete('set null');
            $table->longText('ttd_guru')->nullable();
            $table->string('approval_token', 64)->nullable();
            $table->boolean('is_testing_data')->default(false);
            $table->timestamps();

            $table->index('tanggal');
            $table->index('status');
            $table->index('is_testing_data');
            $table->index('id_guru_piket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispensasi_kolektif');
    }
};
