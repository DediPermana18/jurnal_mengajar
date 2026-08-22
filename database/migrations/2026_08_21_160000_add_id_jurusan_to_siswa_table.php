<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->foreignId('id_jurusan')
                ->nullable()
                ->after('id_kelas')
                ->constrained('jurusan')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['id_jurusan']);
            $table->dropColumn('id_jurusan');
        });
    }
};