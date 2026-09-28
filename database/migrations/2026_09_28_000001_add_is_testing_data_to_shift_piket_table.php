<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shift_piket') && ! Schema::hasColumn('shift_piket', 'is_testing_data')) {
            Schema::table('shift_piket', function (Blueprint $table) {
                $table->boolean('is_testing_data')->default(false);
                $table->index('is_testing_data');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shift_piket') && Schema::hasColumn('shift_piket', 'is_testing_data')) {
            Schema::table('shift_piket', function (Blueprint $table) {
                $table->dropIndex(['is_testing_data']);
                $table->dropColumn('is_testing_data');
            });
        }
    }
};
