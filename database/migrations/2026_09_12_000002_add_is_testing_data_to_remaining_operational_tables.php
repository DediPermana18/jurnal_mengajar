<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel operasional/transaksi yang belum memiliki kolom is_testing_data.
     * (pengaturan_jadwal ditangani migrasi khusus is_testing_data-nya sendiri.)
     */
    private const REMAINING_TABLES = [
        'agenda_rutin',
        'jadwal_pelajaran',
        'jadwal_piket',
        'jam_pelajaran',
        'jam_pulang',
        'penerima_catatan_terlambat',
        'pengurus_ruangan',
    ];

    public function up(): void
    {
        foreach (self::REMAINING_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'is_testing_data')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_testing_data')->default(false);
                $table->index('is_testing_data');
            });
        }
    }

    public function down(): void
    {
        foreach (self::REMAINING_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'is_testing_data')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_testing_data');
            });
        }
    }
};
