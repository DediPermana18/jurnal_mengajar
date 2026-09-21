<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presensi siswa kini dicatat per Jam Pelajaran (JP).
     *
     * Tambahkan referensi ke master data `jam_pelajaran` + denormalisasi `jp_ke`
     * agar struktur per-JP lengkap (siswa_id, tanggal, jam_pelajaran_id, status, keterangan)
     * dan ubah unique key menjadi [id_siswa, tanggal, jam_pelajaran_id].
     */
    public function up(): void
    {
        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->foreignId('jam_pelajaran_id')
                ->nullable()
                ->after('id_kelas')
                ->constrained('jam_pelajaran')
                ->nullOnDelete();

            $table->unsignedSmallInteger('jp_ke')
                ->nullable()
                ->after('jam_pelajaran_id');
        });

        // Unique key baru: satu presensi per siswa per JP per hari.
        // Index biasa id_siswa ditambahkan lebih dulu sebagai pendukung FK
        // (unique lama dipakai MySQL sebagai index pendukung FK id_siswa —
        //  tanpa ini dropIndex uniq menimbulkan error 1553).
        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->index('id_siswa');
            $table->dropUnique(['id_siswa', 'tanggal']);
        });

        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->unique(['id_siswa', 'tanggal', 'jam_pelajaran_id']);
        });

        // Backfill data lama (jika ada di environment lain): lampirkan presensi
        // harian lama ke JP KBM pertama pada kategori hari tanggal tsb.
        $rows = DB::table('presensi_siswa')->get(['id', 'tanggal', 'is_testing_data']);
        foreach ($rows as $row) {
            $kategoriHari = Carbon::parse($row->tanggal)->isFriday() ? 'Jumat' : 'Senin-Kamis';
            $jp = DB::table('jam_pelajaran')
                ->where('kategori_hari', $kategoriHari)
                ->where('jenis', 'kbm')
                ->where('is_testing_data', (bool) $row->is_testing_data)
                ->orderBy('jam_ke')
                ->first();

            if ($jp) {
                DB::table('presensi_siswa')->where('id', $row->id)->update([
                    'jam_pelajaran_id' => $jp->id,
                    'jp_ke' => $jp->jam_ke,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->dropUnique(['id_siswa', 'tanggal', 'jam_pelajaran_id']);
        });

        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->unique(['id_siswa', 'tanggal']);
        });

        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->dropForeign(['jam_pelajaran_id']);
            $table->dropIndex(['id_siswa']);
            $table->dropColumn(['jam_pelajaran_id', 'jp_ke']);
        });
    }
};