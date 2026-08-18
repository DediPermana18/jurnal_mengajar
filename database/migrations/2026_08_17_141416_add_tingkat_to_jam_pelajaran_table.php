<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->enum('tingkat', ['10', '11', '12'])->nullable()->after('kategori_hari');
        });

        // Set default tingkat '10' for existing rows
        DB::table('jam_pelajaran')->whereNull('tingkat')->update(['tingkat' => '10']);
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropColumn('tingkat');
        });
    }
};
