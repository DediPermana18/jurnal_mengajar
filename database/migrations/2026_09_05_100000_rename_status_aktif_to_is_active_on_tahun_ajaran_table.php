<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->renameColumn('status_aktif', 'is_active');
        });

        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->boolean('is_active')->change();
        });

        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->renameColumn('is_active', 'status_aktif');
        });
    }
};
