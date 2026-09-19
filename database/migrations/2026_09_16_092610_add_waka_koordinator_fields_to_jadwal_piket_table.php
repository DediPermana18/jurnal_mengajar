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
        Schema::table('jadwal_piket', function (Blueprint $table) {
            // Waka Piket: Single dropdown (User dengan role/jabatan Waka)
            $table->foreignId('waka_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Sesi Pagi (07.00 - 11.00)
            $table->foreignId('koordinator_pagi_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('petugas_pagi_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Sesi Siang (11.00 - 15.00)
            $table->foreignId('koordinator_siang_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('petugas_siang_user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropColumn([
                'waka_user_id',
                'koordinator_pagi_user_id',
                'petugas_pagi_user_id',
                'koordinator_siang_user_id',
                'petugas_siang_user_id',
            ]);
        });
    }
};
