<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->boolean('jumat_tanpa_pembiasaan')->default(false)->after('tanggal_eksekusi');
            $table->date('tanggal_eksekusi_jumat')->nullable()->after('jumat_tanpa_pembiasaan');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn(['jumat_tanpa_pembiasaan', 'tanggal_eksekusi_jumat']);
        });
    }
};
