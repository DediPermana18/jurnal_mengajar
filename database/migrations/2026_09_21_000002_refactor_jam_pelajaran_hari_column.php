<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom `hari` jika belum ada
        if (! Schema::hasColumn('jam_pelajaran', 'hari')) {
            Schema::table('jam_pelajaran', function (Blueprint $table) {
                $table->string('hari', 20)->nullable()->after('id');
            });
        }

        // 2. Migrasi data existing
        $oldSlots = DB::table('jam_pelajaran')->get();

        foreach ($oldSlots as $slot) {
            $kategori = $slot->kategori_hari ?? $slot->hari ?? 'Senin-Kamis';

            if ($kategori === 'Senin-Kamis') {
                $days = ['Senin', 'Selasa', 'Rabu', 'Kamis'];
                $firstNewId = null;

                foreach ($days as $index => $day) {
                    $newId = DB::table('jam_pelajaran')->insertGetId([
                        'hari'            => $day,
                        'kategori_hari'   => 'Senin-Kamis',
                        'jam_ke'          => $slot->jam_ke,
                        'jam_mulai'       => $slot->jam_mulai,
                        'jam_selesai'     => $slot->jam_selesai,
                        'jenis'           => $slot->jenis,
                        'is_testing_data' => $slot->is_testing_data ?? 0,
                        'created_at'      => $slot->created_at ?? now(),
                        'updated_at'      => $slot->updated_at ?? now(),
                    ]);

                    if ($index === 0) {
                        $firstNewId = $newId;
                    }

                    // Re-link jadwal_pelajaran per hari spesifik
                    DB::table('jadwal_pelajaran')
                        ->where('id_jam', $slot->id)
                        ->where('hari', $day)
                        ->update(['id_jam' => $newId]);
                }

                // Fallback jika ada jadwal_pelajaran yang belum ter-relink
                if ($firstNewId) {
                    DB::table('jadwal_pelajaran')
                        ->where('id_jam', $slot->id)
                        ->update(['id_jam' => $firstNewId]);
                }

                // Hapus slot 'Senin-Kamis' lama
                DB::table('jam_pelajaran')->where('id', $slot->id)->delete();

            } else {
                // Untuk 'Jumat' atau hari spesifik
                $hariVal = in_array($kategori, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true)
                    ? $kategori
                    : 'Jumat';

                DB::table('jam_pelajaran')
                    ->where('id', $slot->id)
                    ->update(['hari' => $hariVal]);
            }
        }

        // 3. Pastikan kolom `hari` NOT NULL
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->string('hari', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Down migration fallback if needed
    }
};
