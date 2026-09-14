<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_kendala', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul', 150);
            $table->text('deskripsi');
            $table->string('foto_bukti')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('prioritas', 10)->default('medium');
            $table->boolean('is_testing_data')->default(false);
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_kendala');
    }
};
