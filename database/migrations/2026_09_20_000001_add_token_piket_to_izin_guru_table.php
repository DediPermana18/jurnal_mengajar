<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Token publik khusus tahap "Menunggu Piket" (quick approve Guru Piket).
     *
     * Sama seperti token_waka / token_kepsek, satu izin mendapat satu token_piket
     * yang dibagikan via broadcast WA ke SELURUH Guru Piket yang bertugas pada
     * tanggal pengajuan. Guru Piket pertama yang membuka/menyetujui link akan
     * memajukan izin; piket lain yang membuka link yang sama akan melihat
     * pemberitahuan "sudah diproses" (anti double approval).
     */
    public function up(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->string('token_piket', 64)->nullable()->unique()->after('token_kepsek');
        });

        // Backfill token untuk data lama agar tautan quick-approve selalu tersedia.
        $rows = DB::table('izin_guru')->whereNull('token_piket')->get(['id']);
        foreach ($rows as $row) {
            DB::table('izin_guru')
                ->where('id', $row->id)
                ->update(['token_piket' => (string) Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->dropUnique(['token_piket']);
            $table->dropColumn('token_piket');
        });
    }
};