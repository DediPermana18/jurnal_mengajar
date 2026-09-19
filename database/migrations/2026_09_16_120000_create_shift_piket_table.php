<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_piket', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedSmallInteger('maksimal_petugas')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        DB::table('shift_piket')->insert([
            ['nama' => 'Pagi', 'jam_mulai' => '07:00:00', 'jam_selesai' => '11:00:00', 'maksimal_petugas' => 4, 'is_active' => true, 'urutan' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Siang', 'jam_mulai' => '11:00:00', 'jam_selesai' => '15:00:00', 'maksimal_petugas' => 4, 'is_active' => true, 'urutan' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_piket');
    }
};
