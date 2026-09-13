<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel transaksional yang diisolasi untuk data testing (sandbox):
     * data dengan is_testing_data = true hanya dapat dikelola Petugas IT / QA Tester.
     */
    protected const TESTING_TABLES = [
        'dispensasi_siswa',
        'jurnal',
        'absensi_jurnal',
        'izin_guru',
        'presensi_siswa',
        'catatan_terlambat',
        'catatan_siswa_bermasalah',
    ];

    public function up(): void
    {
        foreach (self::TESTING_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_testing_data')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('is_testing_data')->default(false);
                    $table->index('is_testing_data');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TESTING_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_testing_data')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropIndex(['is_testing_data']);
                    $table->dropColumn('is_testing_data');
                });
            }
        }
    }
};