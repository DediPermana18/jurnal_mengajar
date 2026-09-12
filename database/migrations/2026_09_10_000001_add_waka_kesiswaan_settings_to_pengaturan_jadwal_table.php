<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->string('nama_waka_kesiswaan', 150)->nullable()->after('no_wa_kepsek');
            $table->string('nip_waka_kesiswaan', 50)->nullable()->after('nama_waka_kesiswaan');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn(['nama_waka_kesiswaan', 'nip_waka_kesiswaan']);
        });
    }
};
