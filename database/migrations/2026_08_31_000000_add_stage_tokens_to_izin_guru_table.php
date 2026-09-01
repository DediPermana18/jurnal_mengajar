<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pisahkan token approval publik menjadi dua token khusus tahap:
     *   - token_waka   : hanya menampilkan form persetujuan/tanda tangan Waka Kurikulum.
     *   - token_kepsek : hanya menampilkan form persetujuan/tanda tangan Kepala Sekolah.
     * Token Kepsek dibuat/dirilis saat proses masuk ke tahap Pending Kepsek,
     * sehingga form Kepsek tidak dapat diakses melalui link token Waka.
     */
    public function up(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->string('token_waka', 64)->nullable()->unique()->after('approval_token');
            $table->string('token_kepsek', 64)->nullable()->unique()->after('token_waka');
        });

        // Backfill data lama: satu token lama dipetakan ke kolom sesuai tahap yang
        // paling relevan agar tautan lama tetap berfungsi tanpa registrasi ganda.
        DB::table('izin_guru')
            ->select(['id', 'approval_token', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (! $row->approval_token) {
                        continue;
                    }

                    if ($row->status === 'pending_kepsek') {
                        DB::table('izin_guru')->where('id', $row->id)
                            ->update(['token_kepsek' => $row->approval_token]);
                    } else {
                        DB::table('izin_guru')->where('id', $row->id)
                            ->update(['token_waka' => $row->approval_token]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->dropUnique(['token_waka']);
            $table->dropUnique(['token_kepsek']);
            $table->dropColumn(['token_waka', 'token_kepsek']);
        });
    }
};
