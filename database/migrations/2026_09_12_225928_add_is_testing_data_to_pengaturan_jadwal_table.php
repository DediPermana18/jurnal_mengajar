<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pengaturan_jadwal', 'is_testing_data')) {
            Schema::table('pengaturan_jadwal', function (Blueprint $table) {
                $table->boolean('is_testing_data')->default(false)->after('nip_waka_kesiswaan');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pengaturan_jadwal', function (Blueprint $table) {
            $table->dropColumn('is_testing_data');
        });
    }
};
