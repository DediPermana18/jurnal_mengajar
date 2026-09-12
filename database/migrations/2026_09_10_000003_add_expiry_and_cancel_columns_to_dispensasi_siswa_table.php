<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk fitur:
     * 1. Auto-Expired Surat Dispensasi (kadaluarsa bila melewati Jam Berangkat + 1 JP).
     * 2. Pembatalan Dispensasi wajib bukti TTD siswa (pembatalan).
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable()->after('status');
            $table->text('ttd_pembatalan')->nullable()->after('ttd_waka');
            $table->timestamp('dibatalkan_at')->nullable()->after('ttd_pembatalan');
            $table->foreignId('dibatalkan_by')
                ->nullable()
                ->after('dibatalkan_at')
                ->constrained('users', 'id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibatalkan_by');
            $table->dropColumn(['expired_at', 'ttd_pembatalan', 'dibatalkan_at']);
        });
    }
};
