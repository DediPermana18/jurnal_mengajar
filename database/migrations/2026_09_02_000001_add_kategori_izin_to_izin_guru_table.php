<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori izin terstruktur (Sakit / Dinas Luar / Urusan Keluarga / Lainnya)
     * disimpan pada kolom 'kategori_izin', rincian tambahan pada 'keterangan'.
     */
    public function up(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->string('kategori_izin')->nullable()->after('alasan');
            $table->text('keterangan')->nullable()->after('kategori_izin');

            $table->index('kategori_izin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('izin_guru', function (Blueprint $table) {
            $table->dropIndex(['kategori_izin']);
            $table->dropColumn(['kategori_izin', 'keterangan']);
        });
    }
};