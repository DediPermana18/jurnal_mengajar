<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Petakan shift ke tingkatan kelas tertentu (Kelas 10/X, Kelas 11/XI, Kelas 12/XII).
     *
     * grade_levels NULL / [] = shift berlaku untuk SEMUA tingkatan (perilaku legacy).
     * Contoh: ["XII"] => Shift hanya melayani Kelas 12; ["X","XI"] => Shift melayani Kelas 10 & 11.
     */
    public function up(): void
    {
        Schema::table('shift_pelajaran', function (Blueprint $table) {
            $table->json('grade_levels')->nullable()->after('is_active')
                ->comment('Tingkatan kelas yang dilayani shift (X/XI/XII); kosong = semua tingkatan');
        });
    }

    public function down(): void
    {
        Schema::table('shift_pelajaran', function (Blueprint $table) {
            $table->dropColumn('grade_levels');
        });
    }
};