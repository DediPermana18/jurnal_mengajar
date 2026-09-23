<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap Piket Harian — dokumen validasi piket yang disahkan oleh Waka Piket.
 *
 * Satu baris per tanggal (unique). Berisi catatan Koordinator Shift Pagi &
 * Siang, catatan Kejadian Luar Biasa (KLB), serta jejak validasi Waka Piket:
 *   - status 'draft'     : rekap dibuka/diisi Koordinator Shift, belum sah.
 *   - status 'validated' : telah disahkan Waka Piket (validated_by/at terisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_piket_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->enum('status', ['draft', 'validated'])->default('draft');

            // Koordinator Shift yang mengisi rekap pada tanggal tsb.
            $table->foreignId('koordinator_pagi_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('koordinator_siang_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Catatan isian Koordinator Shift.
            $table->text('catatan_pagi')->nullable();
            $table->text('catatan_siang')->nullable();

            // Catatan Kejadian Luar Biasa (KLB) harian.
            $table->text('catatan_klb')->nullable();

            // Jejak pengesahan oleh Waka Piket / Kurikulum.
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->boolean('is_testing_data')->default(false);
            $table->timestamps();

            $table->unique('tanggal');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_piket_harian');
    }
};