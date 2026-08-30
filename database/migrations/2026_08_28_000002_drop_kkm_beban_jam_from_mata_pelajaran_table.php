<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn(['kkm', 'beban_jam']);
        });
    }

    public function down(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->unsignedSmallInteger('kkm')->nullable()->default(75)->after('kelompok');
            $table->unsignedSmallInteger('beban_jam')->nullable()->default(2)->after('kkm');
        });
    }
};
