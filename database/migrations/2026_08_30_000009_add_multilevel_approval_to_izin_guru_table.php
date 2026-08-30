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
        // Ubah enum status menjadi alur bertingkat (5 state) - lintas driver.
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->enum('status', [
                'pending_piket',
                'pending_waka',
                'pending_kepsek',
                'disetujui',
                'ditolak',
            ])->default('pending_piket')->change();
        });

        Schema::table('izin_guru', function (Blueprint $table) {
            // Tracker TTD / approval per step
            $table->foreignId('approved_by_piket')->nullable()->after('status')->constrained('users', 'id')->nullOnDelete();
            $table->foreignId('approved_by_waka')->nullable()->after('approved_by_piket')->constrained('users', 'id')->nullOnDelete()->nullable();
            $table->foreignId('approved_by_kepsek')->nullable()->after('approved_by_waka')->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_kepsek');

            // Tanda tangan digital (data URL base64 PNG) dari Canvas
            $table->longText('ttd_guru')->nullable()->after('approved_at');
            $table->longText('ttd_waka')->nullable()->after('ttd_guru');
            $table->longText('ttd_kepsek')->nullable()->after('ttd_waka');

            // Token unik untuk link approval publik (Waka & Kepsek)
            $table->string('approval_token', 64)->nullable()->unique()->after('ttd_kepsek');
        });

        // Migrasi data lama: status 'pending' -> 'pending_piket' (menunggu verifikasi Guru Piket)
        DB::table('izin_guru')->where('status', 'pending')->update(['status' => 'pending_piket']);

        // Backfill token UUID untuk data lama agar link approval publik selalu tersedia.
        $rows = DB::table('izin_guru')->whereNull('approval_token')->get(['id']);
        foreach ($rows as $row) {
            DB::table('izin_guru')
                ->where('id', $row->id)
                ->update(['approval_token' => (string) Str::uuid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->dropUnique(['approval_token']);
            $table->dropForeign(['approved_by_piket']);
            $table->dropForeign(['approved_by_waka']);
            $table->dropForeign(['approved_by_kepsek']);
            $table->dropColumn([
                'approval_token',
                'approved_by_piket',
                'approved_by_waka',
                'approved_by_kepsek',
                'approved_at',
                'ttd_guru',
                'ttd_waka',
                'ttd_kepsek',
            ]);
        });

        Schema::table('izin_guru', function (Blueprint $table) {
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending')->change();
        });
        DB::table('izin_guru')->where('status', 'pending_piket')->update(['status' => 'pending']);
    }
};
