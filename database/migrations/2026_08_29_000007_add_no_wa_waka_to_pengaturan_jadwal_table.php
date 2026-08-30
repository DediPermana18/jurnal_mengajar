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
            $table->string('no_wa_waka')->nullable()->after('tanggal_eksekusi_jumat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn('no_wa_waka');
        });
    }
};