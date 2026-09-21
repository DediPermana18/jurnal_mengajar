<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel settings kunci-nilai global (key-value store) untuk pengaturan
     * aplikasi yang dikelola dari UI — mis. API Token Fonnte (WhatsApp Gateway).
     *
     * Bersifat GLOBAL (bukan per partisi testing): pengaturan gateway harus sama
     * untuk semua bucket data (QA/IT maupun data real).
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};