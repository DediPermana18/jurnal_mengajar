<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->foreignId('id_jadwal')->nullable()->after('id_guru_piket')
                ->constrained('jadwal_pelajaran', 'id')->nullOnDelete();
            $table->foreignId('id_guru')->nullable()->after('id_jadwal')
                ->constrained('users', 'id')->nullOnDelete();

            $table->index('id_guru');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropIndex(['id_guru']);
            $table->dropForeign(['id_guru']);
            $table->dropForeign(['id_jadwal']);
            $table->dropColumn(['id_guru', 'id_jadwal']);
        });
    }
};