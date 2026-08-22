<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Update kultum to pembiasaan if exists
        DB::table('jam_pelajaran')->where('jenis', 'kultum')->update(['jenis' => 'pembiasaan']);

        // 2. Modify table
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->integer('jam_ke')->nullable()->change();
        });

        // Use raw SQL to update enum definition safely in MySQL/MariaDB
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jam_pelajaran MODIFY COLUMN jenis ENUM('kbm', 'upacara', 'pembiasaan', 'istirahat') NOT NULL DEFAULT 'kbm'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jam_pelajaran MODIFY COLUMN jenis ENUM('kbm', 'istirahat', 'upacara', 'kultum') NOT NULL DEFAULT 'kbm'");
        }

        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->integer('jam_ke')->nullable(false)->change();
        });
    }
};
