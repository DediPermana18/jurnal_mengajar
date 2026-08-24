<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            if (!Schema::hasColumn('mata_pelajaran', 'kelompok')) {
                $table->string('kelompok', 100)->nullable()->default('Umum / Kelompok A')->after('kode_mapel');
            }
            if (!Schema::hasColumn('mata_pelajaran', 'kkm')) {
                $table->unsignedSmallInteger('kkm')->nullable()->default(75)->after('kelompok');
            }
            if (!Schema::hasColumn('mata_pelajaran', 'beban_jam')) {
                $table->unsignedSmallInteger('beban_jam')->nullable()->default(2)->after('kkm');
            }
        });
    }

    public function down(): void {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn(['kelompok', 'kkm', 'beban_jam']);
        });
    }
};
