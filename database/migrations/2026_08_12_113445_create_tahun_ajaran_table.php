<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 20);
            $table->enum('semester', ['Ganjil', 'Genap']);
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tahun_ajaran');
    }
};