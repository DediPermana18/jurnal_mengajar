<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautkan baris dispensasi_siswa ke induk transaksi kolektif
     * (satu pengajuan untuk banyak siswa / rombongan).
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->foreignId('dispensasi_kolektif_id')
                ->nullable()
                ->constrained('dispensasi_kolektif', 'id')
                ->cascadeOnDelete();
            $table->index('dispensasi_kolektif_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropForeign(['dispensasi_kolektif_id']);
            $table->dropIndex(['dispensasi_kolektif_id']);
            $table->dropColumn('dispensasi_kolektif_id');
        });
    }
};
