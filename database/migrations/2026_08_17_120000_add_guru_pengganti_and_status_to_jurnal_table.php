<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->foreignId('id_guru')->nullable()->after('id_jadwal')->constrained('users')->cascadeOnDelete();
            $table->foreignId('id_guru_pengganti')->nullable()->after('id_guru')->constrained('users')->nullOnDelete();
            $table->enum('status_kehadiran', ['Hadir', 'Izin', 'Sakit', 'Disposisi'])->default('Hadir')->after('id_guru_pengganti');
        });

        // Backfill id_guru untuk data jurnal lama berdasarkan id_guru di jadwal_pelajaran
        DB::statement("
            UPDATE jurnal j 
            JOIN jadwal_pelajaran jp ON j.id_jadwal = jp.id 
            SET j.id_guru = jp.id_guru 
            WHERE j.id_guru IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal', function (Blueprint $table) {
            $table->dropForeign(['id_guru']);
            $table->dropForeign(['id_guru_pengganti']);
            $table->dropColumn(['id_guru', 'id_guru_pengganti', 'status_kehadiran']);
        });
    }
};
