<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_pulang', function (Blueprint $table) {
            $table->id();
            $table->enum('kategori_hari', ['Senin-Kamis', 'Jumat']);
            $table->string('tingkat', 10); // 'X', 'XI', 'XII'
            $table->unsignedSmallInteger('max_jam_ke')->nullable()->comment('NULL = tidak dibatasi, integer = batas jam KBM terakhir');
            $table->timestamps();

            // Satu konfigurasi per kombinasi kategori hari + tingkat kelas
            $table->unique(['kategori_hari', 'tingkat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_pulang');
    }
};
