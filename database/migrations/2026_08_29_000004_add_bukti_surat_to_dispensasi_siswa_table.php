<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom ttd_siswa dikembalikan fungsinya sebagai tanda tangan digital
     * (data URL base64 preview canvas). File bukti surat upload diarahkan ke
     * kolom baru bukti_surat.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->text('bukti_surat')->nullable()->after('ttd_siswa');
        });

        // Pindahkan path file bukti lama (yang bukan data URL canvas) ke bukti_surat
        DB::table('dispensasi_siswa')
            ->whereNotNull('ttd_siswa')
            ->where('ttd_siswa', 'not like', 'data:%')
            ->update([
                'bukti_surat' => DB::raw('ttd_siswa'),
                'ttd_siswa'   => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan path bukti ke ttd_siswa bila ttd_siswa kosong
        DB::table('dispensasi_siswa')
            ->whereNull('ttd_siswa')
            ->whereNotNull('bukti_surat')
            ->update([
                'ttd_siswa'   => DB::raw('bukti_surat'),
                'bukti_surat' => null,
            ]);

        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropColumn('bukti_surat');
        });
    }
};