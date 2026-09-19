<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->unsignedTinyInteger('minggu_ke')->nullable()->after('hari');
            $table->unsignedTinyInteger('bulan')->nullable()->after('minggu_ke');
            $table->unsignedSmallInteger('tahun')->nullable()->after('bulan');
            $table->index(['tahun', 'bulan', 'minggu_ke', 'hari']);
        });

        $now = now();
        DB::table('jadwal_piket')->whereNull('minggu_ke')->update([
            'minggu_ke' => min(4, (int) ceil($now->day / 7)),
            'bulan' => $now->month,
            'tahun' => $now->year,
        ]);
    }

    public function down(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropIndex(['tahun', 'bulan', 'minggu_ke', 'hari']);
            $table->dropColumn(['minggu_ke', 'bulan', 'tahun']);
        });
    }
};
