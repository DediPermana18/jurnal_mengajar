<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom sub_role
        Schema::table('users', function (Blueprint $table) {
            $table->string('sub_role', 50)->nullable()->after('role');
        });

        // 2. Clean up obsolete kesiswaan/kepala sekolah user records if any exist
        DB::table('users')
            ->whereIn('role', ['admin_kesiswaan', 'kesiswaan', 'kepala_sekolah', 'waka_kesiswaan'])
            ->orWhere('username', 'kesiswaan')
            ->delete();

        // 3. Migrate data: map old role -> new role + sub_role
        $mapping = [
            'super_admin'     => ['role' => 'admin',  'sub_role' => null],
            'admin_tu'        => ['role' => 'admin',  'sub_role' => 'petugas_tu'],
            'admin'           => ['role' => 'admin',  'sub_role' => 'petugas_tu'],
            'admin_kurikulum' => ['role' => 'admin',  'sub_role' => 'waka_kurikulum'],
            'guru_mapel'      => ['role' => 'guru',   'sub_role' => 'guru_mapel'],
            'wali_kelas'      => ['role' => 'guru',   'sub_role' => 'wali_kelas'],
            'guru_piket'      => ['role' => 'guru',   'sub_role' => 'guru_piket'],
            'piket_satpam'    => ['role' => 'admin',  'sub_role' => 'satpam'],
            'guru'            => ['role' => 'guru',   'sub_role' => null],
        ];

        foreach ($mapping as $oldRole => $new) {
            DB::table('users')
                ->where('role', $oldRole)
                ->update([
                    'role'     => $new['role'],
                    'sub_role' => $new['sub_role'],
                ]);
        }

        // 4. Update role enum hanya Accept 'admin' / 'guru'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guru') NOT NULL DEFAULT 'guru'");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sub_role');
        });

        // Restore old role enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin_tu','admin_kurikulum','guru_mapel','wali_kelas','guru_piket','piket_satpam','guru','admin') NOT NULL DEFAULT 'guru_mapel'");
        }
    }
};
