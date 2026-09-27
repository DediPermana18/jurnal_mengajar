<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama kustom perangkat oleh pemilik akun ("Beri Nama Perangkat Ini").
     *
     * Bukan tabel audit — metadata tampilan milik user, boleh di-update/dihapus.
     * security_logs (immutable) TIDAK tersentuh; nama tersimpan per
     * (user_id, fingerprint) dan berlaku untuk SELURUH baris log
     * dengan fingerprint yang sama.
     */
    public function up(): void
    {
        Schema::create('security_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('name', 80)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_devices');
    }
};