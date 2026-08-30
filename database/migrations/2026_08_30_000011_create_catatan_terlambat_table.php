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
        Schema::create('catatan_terlambat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_siswa')->constrained('siswa', 'id')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_masuk');
            $table->string('keterangan', 191)->nullable();
            $table->foreignId('id_satpam')->constrained('users', 'id')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tanggal');
            $table->index(['id_siswa', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catatan_terlambat');
    }
};