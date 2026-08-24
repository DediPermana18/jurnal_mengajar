<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_jadwal', function (Blueprint $table) {
            $table->id();
            $table->boolean('senin_tanpa_upacara')->default(false);
            $table->date('tanggal_eksekusi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_jadwal');
    }
};
