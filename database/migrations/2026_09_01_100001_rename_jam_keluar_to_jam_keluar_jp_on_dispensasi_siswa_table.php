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
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            if (Schema::hasColumn('dispensasi_siswa', 'jam_keluar')) {
                $table->dropColumn('jam_keluar');
            }
            if (!Schema::hasColumn('dispensasi_siswa', 'jam_keluar_jp')) {
                $table->unsignedInteger('jam_keluar_jp')->nullable()->after('jam_ke');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            if (Schema::hasColumn('dispensasi_siswa', 'jam_keluar_jp')) {
                $table->dropColumn('jam_keluar_jp');
            }
            if (!Schema::hasColumn('dispensasi_siswa', 'jam_keluar')) {
                $table->string('jam_keluar', 5)->nullable()->after('jam_ke');
            }
        });
    }
};