<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'mapel_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('mapel_ids');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'mapel_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('mapel_ids')->nullable()->after('sub_role');
            });
        }
    }
};