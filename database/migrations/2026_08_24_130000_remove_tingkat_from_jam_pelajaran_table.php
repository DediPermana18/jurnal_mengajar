<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('jam_pelajaran', 'tingkat')) {
            Schema::table('jam_pelajaran', function (Blueprint $table) {
                $table->dropColumn('tingkat');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('jam_pelajaran', 'tingkat')) {
            Schema::table('jam_pelajaran', function (Blueprint $table) {
                $table->enum('tingkat', ['10', '11', '12'])->nullable()->after('kategori_hari');
            });
        }
    }
};
