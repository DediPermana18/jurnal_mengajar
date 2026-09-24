<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan jam pulang per tingkat kini bersifat per shift:
     * shift_id = 0 berarti "Global" (kelas tanpa shift / legacy).
     * Unique index diperluas agar tiap shift punya setting sendiri-sendiri
     * sehingga batas jam pulang antar-shift tidak saling menimpa.
     */
    public function up(): void
    {
        Schema::table('jam_pulang', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->default(0)->after('id');
        });

        Schema::table('jam_pulang', function (Blueprint $table) {
            $table->dropUnique('jam_pulang_kategori_tingkat_testing_unique');
            $table->unique(
                ['kategori_hari', 'tingkat', 'shift_id', 'is_testing_data'],
                'jam_pulang_kategori_tingkat_shift_testing_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('jam_pulang', function (Blueprint $table) {
            $table->dropUnique('jam_pulang_kategori_tingkat_shift_testing_unique');
            $table->unique(
                ['kategori_hari', 'tingkat', 'is_testing_data'],
                'jam_pulang_kategori_tingkat_testing_unique'
            );
            $table->dropColumn('shift_id');
        });
    }
};