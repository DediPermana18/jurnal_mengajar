<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi Agenda Rutin (Upacara Bendera & Pembiasaan Jumat) kini
     * terisolasi per shift: shift_id = 0 berarti Global (mode penjadwalan
     * global / kelas tanpa shift), shift_id = id shift berarti khusus shift itu.
     *
     * Sebelum migrasi, seluruh record agenda_rutin bersifat global sehingga
     * status Aktif/Non-Aktif dari satu shift bocor ke shift lain.
     */
    public function up(): void
    {
        Schema::table('agenda_rutin', function (Blueprint $table) {
            $table->dropUnique('agenda_rutin_hari_jam_ke_testing_unique');
            $table->unsignedBigInteger('shift_id')->default(0)->index()->after('jam_ke');
            $table->unique(
                ['hari', 'jam_ke', 'shift_id', 'is_testing_data'],
                'agenda_rutin_hari_shift_testing_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('agenda_rutin', function (Blueprint $table) {
            $table->dropUnique('agenda_rutin_hari_shift_testing_unique');
            $table->dropIndex(['shift_id']);
            $table->dropColumn('shift_id');
            $table->unique(['hari', 'jam_ke', 'is_testing_data'], 'agenda_rutin_hari_jam_ke_testing_unique');
        });
    }
};