<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perluasan tabel security_logs (TETAP append-only / immutable):
     *  - device_fingerprint : sidik jari perangkat (hash stabil) untuk membedakan
     *                         dua perangkat dengan nama generik yang sama.
     *  - device_meta        : komponen mentah (model, OS, screen, timezone, dll).
     *  - is_unknown_device  : bendera saat login yang TIDAK pernah tercatat
     *                         dengan fingerprint ini sebelumnya (diset SEKALI
     *                         saat insert — tidak pernah diubah sesudahnya).
     */
    public function up(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)
                ->nullable()
                ->index()
                ->after('device_name');

            $table->json('device_meta')
                ->nullable()
                ->after('device_fingerprint');

            $table->boolean('is_unknown_device')
                ->default(false)
                ->after('device_meta');
        });
    }

    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->dropColumn(['device_fingerprint', 'device_meta', 'is_unknown_device']);
        });
    }
};