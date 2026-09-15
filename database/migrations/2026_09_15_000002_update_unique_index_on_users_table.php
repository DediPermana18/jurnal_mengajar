<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop index unique lama pada username
                $table->dropUnique(['username']);
                
                // Buat composite unique index baru pada username dan is_testing_data
                $table->unique(['username', 'is_testing_data'], 'users_username_is_testing_data_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_username_is_testing_data_unique');
                $table->unique(['username']);
            });
        }
    }
};
