<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_rutin', function (Blueprint $table) {
            $table->id();
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);
            $table->unsignedSmallInteger('jam_ke')->default(1);
            $table->string('nama_agenda', 100)->default('Upacara Bendera');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hari', 'jam_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_rutin');
    }
};
