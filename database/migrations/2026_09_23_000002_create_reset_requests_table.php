<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan reset kredensial (lupa sandi / lupa kode aktivasi).
     *
     * Alur: pemohon mengisi formulir publik + tanda tangan digital (canvas),
     * Admin TU memverifikasi via panel, lalu membuat tautan reset unik
     * ber-token (berlaku 24 jam) yang dikirim ke pemohon.
     *
     * Isolasi data testing mengikuti konvensi tabel transaksional lain
     * (kolom is_testing_data + global scope TestingDataScope).
     */
    public function up(): void
    {
        Schema::create('reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('jenis_pengajuan', ['lupa_sandi', 'lupa_kode_aktivasi']);
            // Data URI Base64 gambar tanda tangan (canvas) — bisa besar.
            $table->longText('tanda_tangan');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reset_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->boolean('is_testing_data')->default(false)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reset_requests');
    }
};