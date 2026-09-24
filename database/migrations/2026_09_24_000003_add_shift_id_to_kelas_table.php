<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelas terikat pada satu shift (shift_id NULL = kelas global / legacy).
     * Menentukan jam pelajaran mana yang berlaku & batas jam pulang kelas tsb.
     */
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('id_jurusan');
            $table->foreign('shift_id')
                ->references('id')->on('shift_pelajaran')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};