<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->boolean('maintenance_mode')->default(false)->after('nip_waka_kesiswaan');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn('maintenance_mode');
        });
    }
};
