<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel operasional/transaksi yang belum memiliki kolom is_testing.
     */
    private const REMAINING_TABLES = [
        'agenda_rutin',
        'jadwal_pelajaran',
        'jadwal_piket',
        'jam_pelajaran',
        'jam_pulang',
        'penerima_catatan_terlambat',
        'pengaturan_jadwal',
        'pengurus_ruangan',
    ];

    public function up(): void
    {
        foreach (self::REMAINING_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'is_testing')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                // Posisi kolom: setelah updated_at (MySQL hanya mendukung AFTER).
                $table->boolean('is_testing')->default(false)->after('updated_at');

                $table->index('is_testing');
            });
        }
    }

    public function down(): void
    {
        foreach (self::REMAINING_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'is_testing')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                // MySQL otomatis menghapus index yang hanya menempel pada kolom ini.
                $table->dropColumn('is_testing');
            });
        }
    }
};
