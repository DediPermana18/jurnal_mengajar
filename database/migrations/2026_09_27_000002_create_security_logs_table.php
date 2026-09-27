<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak digital (audit trail) login perangkat — Tabel IMMUTABLE (append-only).
     *
     * Setiap login BERHASIL menambah SATU baris baru. Baris TIDAK PERNAH
     * di-update maupun di-hapus (kebijakan: tidak ada fungsi UPDATE/DELETE
     * untuk tabel ini di controller manapun). Endpoint "Perangkat & Keamanan"
     * hanya membaca baris-baris ini; pemutusan sesi berjalan dilakukan lewat
     * tabel `sessions` + perpindahan ikatan current_session_id, bukan dengan
     * menyentuh security_logs.
     *
     * Kolom `session_lock` & `session_id` (wajib ada) dipakai untuk menendang
     * perangkat asing: lock token device (single_device_lock) + ID sesi HTTP
     * Laravel yang valid untuk menghapus baris car sesi dari tabel `sessions`.
     */
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_name', 191)->nullable();
            $table->timestamp('login_at')->useCurrent();
            $table->boolean('is_current_session')->default(true);
            $table->string('session_lock', 64)->nullable()->index();
            $table->string('session_id', 255)->nullable()->index();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};