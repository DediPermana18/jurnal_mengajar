<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unique index lama hanya (hari, jam_ke) sehingga data real
     * (is_testing_data = false) dan data testing (is_testing_data = true)
     * tidak bisa berdampingan pada kombinasi yang sama -> error duplicate entry
     * saat Petugas IT / QA Tester menyimpan data pengujian.
     */
    public function up(): void
    {
        Schema::table('agenda_rutin', function (Blueprint $table) {
            $table->dropUnique('agenda_rutin_hari_jam_ke_unique');
            $table->unique(
                ['hari', 'jam_ke', 'is_testing_data'],
                'agenda_rutin_hari_jam_ke_testing_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agenda_rutin', function (Blueprint $table) {
            $table->dropUnique('agenda_rutin_hari_jam_ke_testing_unique');
            $table->unique(['hari', 'jam_ke']);
        });
    }
};