<?php

namespace Database\Seeders;

use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Ruangan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JadwalPelajaranSeeder extends Seeder
{
    /**
     * Plotting Plotting Jadwal KBM Mingguan (Senin - Jumat) untuk Kelas & Guru
     */
    public function run(): void
    {
        $tahunAktif = TahunAjaran::where('is_active', true)->first() ?? TahunAjaran::first();
        if (!$tahunAktif) {
            $this->command->warn(' [JadwalPelajaranSeeder] Tahun ajaran aktif tidak ditemukan.');
            return;
        }

        $ruanganDefault = Ruangan::where('kode_ruangan', 'R-01')->first();
        $labKomputer   = Ruangan::where('kode_ruangan', 'LAB-01')->first();
        $studioDkv     = Ruangan::where('kode_ruangan', 'STD-01')->first();

        // Sample Plotting Matrix
        $jadwalMatrix = [
            // SENIN
            ['kelas' => 'X DKV 1',  'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-DKV-01', 'guru' => 'eko.prasetyo', 'ruangan' => $studioDkv?->id],
            ['kelas' => 'X DKV 1',  'hari' => 'Senin', 'jam_mulai' => 5, 'jam_selesai' => 7, 'mapel' => 'MPL-UM-04',  'guru' => 'siti.rahmawati', 'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XI TKJ 1', 'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-TKJ-01', 'guru' => 'ahmad.fauzi',  'ruangan' => $labKomputer?->id],
            ['kelas' => 'XII AKL 1','hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-AKL-01', 'guru' => 'rina.wulandari', 'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'X TKJ 1',  'hari' => 'Senin', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-UM-03',  'guru' => 'budi.santoso',  'ruangan' => $ruanganDefault?->id],

            // SELASA
            ['kelas' => 'X DKV 1',  'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-UM-01', 'guru' => 'bambang.hermanto', 'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XI TKJ 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-TKJ-02', 'guru' => 'budi.santoso',   'ruangan' => $labKomputer?->id],
            ['kelas' => 'XII AKL 1','hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-AKL-02', 'guru' => 'rina.wulandari', 'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XI DKV 1', 'hari' => 'Selasa', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-DKV-02', 'guru' => 'hendra.wijaya', 'ruangan' => $studioDkv?->id],

            // RABU
            ['kelas' => 'X DKV 1',  'hari' => 'Rabu', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-UM-05', 'guru' => 'dewi.lestari',    'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XI TKJ 1', 'hari' => 'Rabu', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-UM-06', 'guru' => 'agus.setiawan',   'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XII AKL 1','hari' => 'Rabu', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-AKL-03', 'guru' => 'retno.utami',     'ruangan' => $ruanganDefault?->id],

            // KAMIS
            ['kelas' => 'X DKV 1',  'hari' => 'Kamis', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-DKV-03', 'guru' => 'eko.prasetyo',   'ruangan' => $studioDkv?->id],
            ['kelas' => 'XI TKJ 1', 'hari' => 'Kamis', 'jam_mulai' => 1, 'jam_selesai' => 4, 'mapel' => 'MPL-TKJ-03', 'guru' => 'ahmad.fauzi',   'ruangan' => $labKomputer?->id],

            // JUMAT
            ['kelas' => 'X DKV 1',  'hari' => 'Jumat', 'jam_mulai' => 1, 'jam_selesai' => 2, 'mapel' => 'MPL-ML-01', 'guru' => 'dewi.lestari',    'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XI TKJ 1', 'hari' => 'Jumat', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-UM-02', 'guru' => 'bambang.hermanto', 'ruangan' => $ruanganDefault?->id],
            ['kelas' => 'XII AKL 1','hari' => 'Jumat', 'jam_mulai' => 1, 'jam_selesai' => 3, 'mapel' => 'MPL-UM-07', 'guru' => 'agus.setiawan',   'ruangan' => $ruanganDefault?->id],
        ];

        $totalJadwal = 0;

        foreach ($jadwalMatrix as $item) {
            [$tingkat, $namaKelas] = explode(' ', $item['kelas'], 2);

            $kelasObj = Kelas::where('tingkat', $tingkat)->where('nama_kelas', $namaKelas)->first();
            $mapelObj = MataPelajaran::where('kode_mapel', $item['mapel'])->first();
            $guruObj  = User::where('username', $item['guru'])->first();

            if (!$kelasObj || !$mapelObj || !$guruObj) {
                continue;
            }

            $kategoriHari = ($item['hari'] === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
            $targetSlots  = JamPelajaran::where('kategori_hari', $kategoriHari)
                ->whereBetween('jam_ke', [$item['jam_mulai'], $item['jam_selesai']])
                ->get();

            $groupId = (string) Str::uuid();

            foreach ($targetSlots as $slot) {
                JadwalPelajaran::withTrashed()->updateOrCreate(
                    [
                        'id_kelas'        => $kelasObj->id,
                        'hari'            => $item['hari'],
                        'id_jam'          => $slot->id,
                        'id_tahun_ajaran' => $tahunAktif->id,
                    ],
                    [
                        'group_id'   => $groupId,
                        'id_mapel'   => $mapelObj->id,
                        'id_guru'    => $guruObj->id,
                        'id_ruangan' => $item['ruangan'],
                        'deleted_at' => null,
                    ]
                );
                $totalJadwal++;
            }
        }

        $this->command->info(" [JadwalPelajaranSeeder] {$totalJadwal} Plotting Slot Jadwal KBM berhasil dibuat/diperbarui.");
    }
}
