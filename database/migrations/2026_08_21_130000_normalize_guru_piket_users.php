<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('sub_role', 'guru_piket')
            ->update([
                'role' => 'guru',
                'sub_role' => 'guru',
            ]);
    }

    public function down(): void
    {
        // Penugasan piket tidak dikembalikan ke role user karena bersifat dinamis.
    }
};