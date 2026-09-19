<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unique index lama hanya (kategori_hari, tingkat) sehingga data real
     * (is_testing_data = false) dan data testing (is_testing_data = true)
     * tidak bisa berdampingan pada kombinasi yang sama -> error duplicate entry
     * saat Petugas IT / QA Tester menyimpan data pengujian.
     */
    public function up(): void
    {
        Schema::table('jam_pulang', function (Blueprint $table) {
            $table->dropUnique('jam_pulang_kategori_hari_tingkat_unique');
            $table->unique(
                ['kategori_hari', 'tingkat', 'is_testing_data'],
                'jam_pulang_kategori_tingkat_testing_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_pulang', function (Blueprint $table) {
            $table->dropUnique('jam_pulang_kategori_tingkat_testing_unique');
            $table->unique(['kategori_hari', 'tingkat']);
        });
    }
};