<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'jurusan',
            'mata_pelajaran',
            'ruangans',
            'tahun_ajaran',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'is_testing_data')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('is_testing_data')->default(false)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'jurusan',
            'mata_pelajaran',
            'ruangans',
            'tahun_ajaran',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'is_testing_data')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('is_testing_data');
                });
            }
        }
    }
};
