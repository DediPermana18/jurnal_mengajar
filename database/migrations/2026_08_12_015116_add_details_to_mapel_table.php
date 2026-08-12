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
        Schema::table('mapel', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kelas')->nullable()->after('id_mapel');
            $table->unsignedBigInteger('id_guru')->nullable()->after('id_kelas');
            $table->string('jam_ke', 50)->nullable()->after('nama_mapel');
            $table->enum('status_guru', ['Masuk Kelas', 'Tidak Hadir', 'Tugas', 'Hadir', 'Izin', 'Sakit'])->default('Masuk Kelas')->after('jam_ke');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapel', function (Blueprint $table) {
            $table->dropColumn(['id_kelas', 'id_guru', 'jam_ke', 'status_guru']);
        });
    }
};
