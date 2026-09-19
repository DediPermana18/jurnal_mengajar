<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('hari')->constrained('shift_piket')->nullOnDelete();
            $table->index(['hari', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
