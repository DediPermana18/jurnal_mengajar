<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ruangans', function (Blueprint $table) {
            $table->id();
            $table->string('kode_ruangan', 20)->unique();
            $table->string('nama_ruangan', 100);
            $table->string('lokasi', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ruangans');
    }
};
