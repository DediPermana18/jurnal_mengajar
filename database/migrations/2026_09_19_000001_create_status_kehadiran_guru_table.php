<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel status kehadiran guru harian — otomatis terisi saat pengajuan izin
     * guru mencapai status final 'disetujui', dan dapat di-override manual oleh
     * Guru Piket pada Portal Guru Piket (manual override lapangan).
     *
     * Guru tanpa record pada suatu tanggal dianggap berstatus 'Hadir'.
     */
    public function up(): void
    {
        Schema::create('status_kehadiran_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Dinas Luar'])->default('Hadir');
            $table->text('keterangan')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_testing_data')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
            $table->index(['tanggal', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_kehadiran_guru');
    }
};