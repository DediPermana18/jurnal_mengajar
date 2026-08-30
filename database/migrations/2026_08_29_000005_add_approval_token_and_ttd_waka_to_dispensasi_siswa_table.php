<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->string('approval_token', 64)->nullable()->unique()->after('ttd_siswa');
            $table->text('ttd_waka')->nullable()->after('approval_token');
        });

        // Backfill token UUID untuk data lama agar link approval publik selalu tersedia.
        $rows = DB::table('dispensasi_siswa')->whereNull('approval_token')->get(['id']);
        foreach ($rows as $row) {
            DB::table('dispensasi_siswa')
                ->where('id', $row->id)
                ->update(['approval_token' => (string) Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropUnique(['approval_token']);
            $table->dropColumn(['approval_token', 'ttd_waka']);
        });
    }
};