<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->enum('jenis', ['kbm', 'istirahat', 'upacara', 'kultum'])
                  ->default('kbm')
                  ->after('jam_selesai');
        });

        // Set default value for existing rows
        \DB::table('jam_pelajaran')->update(['jenis' => 'kbm']);
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
