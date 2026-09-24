<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slot jam pelajaran kini dapat dimiliki oleh sebuah shift.
     * NULL = slot global (berlaku untuk kelas mana pun / legacy).
     */
    public function up(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('hari');
            $table->foreign('shift_id')
                ->references('id')->on('shift_pelajaran')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};