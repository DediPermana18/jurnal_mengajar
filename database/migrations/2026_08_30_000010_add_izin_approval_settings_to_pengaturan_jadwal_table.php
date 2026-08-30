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
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->tinyInteger('izin_approval_level')->unsigned()->default(3)->after('no_wa_waka');
            $table->string('no_wa_kepsek')->nullable()->after('izin_approval_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn(['izin_approval_level', 'no_wa_kepsek']);
        });
    }
};
