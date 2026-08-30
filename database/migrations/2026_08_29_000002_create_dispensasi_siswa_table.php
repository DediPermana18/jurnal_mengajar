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
        Schema::create('dispensasi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_siswa')->constrained('siswa', 'id')->cascadeOnDelete();
            $table->foreignId('id_guru_piket')->constrained('users', 'id')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jam_ke')->nullable();
            $table->text('alasan');
            $table->string('status')->default('disetujui');
            $table->text('ttd_siswa')->nullable();
            $table->timestamps();

            $table->index('tanggal');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispensasi_siswa');
    }
};