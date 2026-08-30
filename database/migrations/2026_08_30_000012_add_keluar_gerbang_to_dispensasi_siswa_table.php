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
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->timestamp('keluar_gerbang_at')->nullable();
            $table->foreignId('keluar_gerbang_by')->nullable()->constrained('users', 'id')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispensasi_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('keluar_gerbang_by');
            $table->dropColumn('keluar_gerbang_at');
        });
    }
};