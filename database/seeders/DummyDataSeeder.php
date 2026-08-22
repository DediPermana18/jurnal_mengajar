<?php

namespace Database\Seeders;

use App\Models\AbsensiJurnal;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Tahun Ajaran
        $tahunAktif = TahunAjaran::updateOrCreate(
            ['tahun_ajaran' => '2024/2025', 'semester' => 'Ganjil'],
            ['status_aktif' => true]
        );

        TahunAjaran::updateOrCreate(
            ['tahun_ajaran' => '2024/2025', 'semester' => 'Genap'],
            ['status_aktif' => false]
        );

        // 2. Seed Mata Pelajaran
        $mapel = collect([
            'MTK-01' => 'Matematika',
            'BIN-01' => 'Bahasa Indonesia',
            'BIG-01' => 'Bahasa Inggris',
            'INF-01' => 'Informatika',
            'FIS-01' => 'Fisika',
            'PWB-01' => 'Pemrograman Web',
            'ADM-01' => 'Administrasi Server',
        ])->map(fn ($nama, $kode) => MataPelajaran::updateOrCreate(
            ['kode_mapel' => $kode],
            ['nama_mapel' => $nama]
        ));

        // 3. Seed Jam Pelajaran
        $jamSeninKamis = [
            ['jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:45:00'],
            ['jam_ke' => 2, 'jam_mulai' => '07:45:00', 'jam_selesai' => '08:30:00'],
            ['jam_ke' => 2, 'jam_mulai' => '13:00:00', 'jam_selesai' => '16:00:00'], 
            ['jam_ke' => 3, 'jam_mulai' => '20:00:00', 'jam_selesai' => '22:00:00'], // Buat tes nanti malam jam 9
            ['jam_ke' => 4, 'jam_mulai' => '22:00:00', 'jam_selesai' => '23:59:00'],
        ];

        foreach ($jamSeninKamis as $j) {
            JamPelajaran::updateOrCreate(
                ['kategori_hari' => 'Senin-Kamis', 'jam_ke' => $j['jam_ke']],
                ['jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai']]
            );
        }

        $jamJumat = [
            ['jam_ke' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '07:40:00'],
            ['jam_ke' => 2, 'jam_mulai' => '07:40:00', 'jam_selesai' => '08:20:00'],
            ['jam_ke' => 3, 'jam_mulai' => '08:20:00', 'jam_selesai' => '09:00:00'],
            ['jam_ke' => 4, 'jam_mulai' => '09:15:00', 'jam_selesai' => '09:55:00'],
        ];

        foreach ($jamJumat as $j) {
            JamPelajaran::updateOrCreate(
                ['kategori_hari' => 'Jumat', 'jam_ke' => $j['jam_ke']],
                ['jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai']]
            );
        }

        // Ambil ID Guru
        $guruBudi  = User::whereIn('username', ['gurubudi', 'gurbudi', 'guru'])->first();
        $guruAhmad = User::where('username', 'gurahmad')->first();
        $guruSiti  = User::where('username', 'gurupiket')->first();

        $jurusanRpl = Jurusan::where('kode_jurusan', 'RPL')->first();
        $jurusanTkj = Jurusan::where('kode_jurusan', 'TKJ')->first();

        // 4. Seed Kelas
        $kelas10 = Kelas::updateOrCreate(
            ['nama_kelas' => 'IPA 1', 'tingkat' => 'X'],
            ['tingkat' => 'X', 'id_wali_kelas' => $guruBudi?->id, 'id_jurusan' => null]
        );

        $kelas11 = Kelas::updateOrCreate(
            ['nama_kelas' => 'IPA 1', 'tingkat' => 'XI'],
            ['tingkat' => 'XI', 'id_wali_kelas' => $guruSiti?->id, 'id_jurusan' => null]
        );

        $kelas12 = Kelas::updateOrCreate(
            ['nama_kelas' => 'IPA 1', 'tingkat' => 'XII'],
            ['tingkat' => 'XII', 'id_wali_kelas' => $guruAhmad?->id, 'id_jurusan' => null]
        );

        $kelas12Rpl = Kelas::updateOrCreate(
            ['nama_kelas' => 'RPL 1', 'tingkat' => 'XII'],
            ['tingkat' => 'XII', 'id_wali_kelas' => $guruAhmad?->id, 'id_jurusan' => $jurusanRpl?->id]
        );

        $kelas11Tkj = Kelas::updateOrCreate(
            ['nama_kelas' => 'TKJ 2', 'tingkat' => 'XI'],
            ['tingkat' => 'XI', 'id_wali_kelas' => $guruBudi?->id, 'id_jurusan' => $jurusanTkj?->id]
        );

        $kelas10Rpl = Kelas::updateOrCreate(
            ['nama_kelas' => 'RPL 2', 'tingkat' => 'X'],
            ['tingkat' => 'X', 'id_wali_kelas' => $guruBudi?->id, 'id_jurusan' => $jurusanRpl?->id]
        );

        // 5. Seed Siswa
        $siswaData = [
            ['nisn' => '0051234001', 'nis' => '241001', 'nama' => 'Aditya Pratama', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234002', 'nis' => '241002', 'nama' => 'Anisa Putri', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234003', 'nis' => '241003', 'nama' => 'Bagus Setiawan', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234004', 'nis' => '241004', 'nama' => 'Citra Dewi', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234005', 'nis' => '241005', 'nama' => 'Dwi Cahyo', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234001', 'nis' => '231001', 'nama' => 'Eka Wijaya', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas11->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234002', 'nis' => '231002', 'nama' => 'Fadhilah Nur', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas11->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234003', 'nis' => '231003', 'nama' => 'Gilang Ramadhan', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas11Tkj->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0041234004', 'nis' => '231004', 'nama' => 'Hana Safitri', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas11Tkj->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0031234001', 'nis' => '221001', 'nama' => 'Indra Kusuma', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas12Rpl->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0031234002', 'nis' => '221002', 'nama' => 'Jihan Maharani', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas12Rpl->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0031234003', 'nis' => '221003', 'nama' => 'Kevin Pratama', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas12Rpl->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0031234004', 'nis' => '221004', 'nama' => 'Lestari Ayu', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas12Rpl->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234006', 'nis' => '241006', 'nama' => 'Maulana Rizki', 'jenis_kelamin' => 'L', 'id_kelas' => $kelas10Rpl->id, 'status_siswa' => 'Aktif'],
            ['nisn' => '0051234007', 'nis' => '241007', 'nama' => 'Nadia Salsabila', 'jenis_kelamin' => 'P', 'id_kelas' => $kelas10Rpl->id, 'status_siswa' => 'Aktif'],
        ];

        foreach ($siswaData as $s) {
            $s['id_jurusan'] = Kelas::find($s['id_kelas'])?->id_jurusan;
            Siswa::updateOrCreate(['nisn' => $s['nisn']], $s);
        }

        // 6. Seed Jadwal Pelajaran Dummy
        $kelasMap = [
            'X IPA 1'   => $kelas10->id,
            'XI IPA 1'  => $kelas11->id,
            'XII IPA 1' => $kelas12->id,
            'XII RPL 1' => $kelas12Rpl->id,
            'XI TKJ 2'  => $kelas11Tkj->id,
            'X RPL 2'   => $kelas10Rpl->id,
        ];

        $guruMap = [
            'gurubudi'  => $guruBudi?->id,
            'gurahmad'  => $guruAhmad?->id,
            'gurupiket' => $guruSiti?->id,
        ];

        $hariIni = $this->hariIndonesia();

        $jadwalDummy = [
            // ── SENIN ──
            ['kelas' => 'X IPA 1',   'hari' => 'Senin', 'jam_ke' => 1, 'kategori' => 'Senin-Kamis', 'mapel' => 'MTK-01', 'guru' => 'gurubudi'],
            ['kelas' => 'X IPA 1',   'hari' => 'Senin', 'jam_ke' => 2, 'kategori' => 'Senin-Kamis', 'mapel' => 'BIN-01', 'guru' => 'gurupiket'],
            ['kelas' => 'X IPA 1',   'hari' => 'Senin', 'jam_ke' => 3, 'kategori' => 'Senin-Kamis', 'mapel' => 'BIG-01', 'guru' => 'gurahmad'],
            ['kelas' => 'XII RPL 1', 'hari' => 'Senin', 'jam_ke' => 4, 'kategori' => 'Senin-Kamis', 'mapel' => 'PWB-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI TKJ 2',  'hari' => 'Senin', 'jam_ke' => 5, 'kategori' => 'Senin-Kamis', 'mapel' => 'ADM-01', 'guru' => 'gurubudi'],

            // ── SELASA ──
            ['kelas' => 'XI IPA 1',  'hari' => 'Selasa', 'jam_ke' => 1, 'kategori' => 'Senin-Kamis', 'mapel' => 'FIS-01', 'guru' => 'gurahmad'],
            ['kelas' => 'X IPA 1',   'hari' => 'Selasa', 'jam_ke' => 2, 'kategori' => 'Senin-Kamis', 'mapel' => 'MTK-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XII RPL 1', 'hari' => 'Selasa', 'jam_ke' => 3, 'kategori' => 'Senin-Kamis', 'mapel' => 'INF-01', 'guru' => 'gurubudi'],
            ['kelas' => 'X RPL 2',   'hari' => 'Selasa', 'jam_ke' => 4, 'kategori' => 'Senin-Kamis', 'mapel' => 'PWB-01', 'guru' => 'gurubudi'],

            // ── RABU ──
            ['kelas' => 'X IPA 1',   'hari' => 'Rabu', 'jam_ke' => 1, 'kategori' => 'Senin-Kamis', 'mapel' => 'INF-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI TKJ 2',  'hari' => 'Rabu', 'jam_ke' => 2, 'kategori' => 'Senin-Kamis', 'mapel' => 'MTK-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XII RPL 1', 'hari' => 'Rabu', 'jam_ke' => 3, 'kategori' => 'Senin-Kamis', 'mapel' => 'PWB-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI IPA 1',  'hari' => 'Rabu', 'jam_ke' => 4, 'kategori' => 'Senin-Kamis', 'mapel' => 'BIN-01', 'guru' => 'gurupiket'],

            // ── KAMIS ──
            ['kelas' => 'XII RPL 1', 'hari' => 'Kamis', 'jam_ke' => 1, 'kategori' => 'Senin-Kamis', 'mapel' => 'PWB-01', 'guru' => 'gurubudi'],
            ['kelas' => 'X IPA 1',   'hari' => 'Kamis', 'jam_ke' => 2, 'kategori' => 'Senin-Kamis', 'mapel' => 'FIS-01', 'guru' => 'gurahmad'],
            ['kelas' => 'XI TKJ 2',  'hari' => 'Kamis', 'jam_ke' => 3, 'kategori' => 'Senin-Kamis', 'mapel' => 'ADM-01', 'guru' => 'gurubudi'],
            ['kelas' => 'X RPL 2',   'hari' => 'Kamis', 'jam_ke' => 4, 'kategori' => 'Senin-Kamis', 'mapel' => 'INF-01', 'guru' => 'gurubudi'],

            // ── JUMAT ──
            ['kelas' => 'X IPA 1',   'hari' => 'Jumat', 'jam_ke' => 1, 'kategori' => 'Jumat', 'mapel' => 'MTK-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XII RPL 1', 'hari' => 'Jumat', 'jam_ke' => 2, 'kategori' => 'Jumat', 'mapel' => 'PWB-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI TKJ 2',  'hari' => 'Jumat', 'jam_ke' => 3, 'kategori' => 'Jumat', 'mapel' => 'BIG-01', 'guru' => 'gurahmad'],

            // ── SABTU (jadwal hari ini untuk demo jurnal guru) ──
            ['kelas' => 'XII RPL 1', 'hari' => 'Sabtu', 'jam_ke' => 1, 'kategori' => 'Senin-Kamis', 'mapel' => 'PWB-01', 'guru' => 'gurubudi', 'jurnal_hari_ini' => true],
            ['kelas' => 'X IPA 1',   'hari' => 'Sabtu', 'jam_ke' => 2, 'kategori' => 'Senin-Kamis', 'mapel' => 'MTK-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI TKJ 2',  'hari' => 'Sabtu', 'jam_ke' => 3, 'kategori' => 'Senin-Kamis', 'mapel' => 'ADM-01', 'guru' => 'gurubudi'],
            ['kelas' => 'X RPL 2',   'hari' => 'Sabtu', 'jam_ke' => 4, 'kategori' => 'Senin-Kamis', 'mapel' => 'INF-01', 'guru' => 'gurubudi'],
            ['kelas' => 'XI IPA 1',  'hari' => 'Sabtu', 'jam_ke' => 5, 'kategori' => 'Senin-Kamis', 'mapel' => 'FIS-01', 'guru' => 'gurahmad'],
        ];

        $jadwalHariIniTerisi = null;

        foreach ($jadwalDummy as $item) {
            if (!$tahunAktif) {
                continue;
            }

            $idKelas = $kelasMap[$item['kelas']] ?? null;
            $idGuru  = $guruMap[$item['guru']] ?? null;
            $idMapel = $mapel[$item['mapel']]->id ?? null;

            $jam = JamPelajaran::where('kategori_hari', $item['kategori'])
                ->where('jam_ke', $item['jam_ke'])
                ->first();

            if (!$jam || !$idKelas || !$idGuru || !$idMapel) {
                continue;
            }

            $jadwal = JadwalPelajaran::updateOrCreate(
                [
                    'id_kelas'        => $idKelas,
                    'hari'            => $item['hari'],
                    'id_jam'          => $jam->id,
                    'id_tahun_ajaran' => $tahunAktif->id,
                ],
                [
                    'group_id' => Str::uuid()->toString(),
                    'id_mapel' => $idMapel,
                    'id_guru'  => $idGuru,
                ]
            );

            // Jurnal contoh: 1 jadwal hari ini sudah terisi (untuk demo status locking)
            if (!empty($item['jurnal_hari_ini']) && $item['hari'] === $hariIni) {
                $jadwalHariIniTerisi = $jadwal;
            }
        }

        // 7. Seed Jurnal & Absensi contoh
        $today = Carbon::today()->toDateString();

        // Jurnal Senin (tanggal Senin terakhir, bukan hari ini kecuali memang Senin)
        $seninTerakhir = Carbon::today()->previous('Monday')->toDateString();
        $jadwalSeninMtk = JadwalPelajaran::where('hari', 'Senin')
            ->whereHas('jamPelajaran', fn ($q) => $q->where('jam_ke', 1))
            ->whereHas('mataPelajaran', fn ($q) => $q->where('kode_mapel', 'MTK-01'))
            ->where('id_kelas', $kelas10->id)
            ->first();

        if ($jadwalSeninMtk) {
            $jurnalSenin = Jurnal::updateOrCreate(
                ['id_jadwal' => $jadwalSeninMtk->id, 'tanggal' => $seninTerakhir],
                [
                    'materi'           => 'Persamaan dan Pertidaksamaan Linear Satu Variabel',
                    'catatan_kejadian' => 'Siswa antusias dan aktif berdiskusi.',
                    'foto_kegiatan'    => null,
                    'waktu_isi'        => Carbon::parse($seninTerakhir)->setTime(8, 15),
                ]
            );

            $siswasK10 = Siswa::where('id_kelas', $kelas10->id)->get();
            $statuses = ['Hadir', 'Hadir', 'Hadir', 'Izin', 'Hadir'];

            foreach ($siswasK10 as $idx => $sis) {
                AbsensiJurnal::updateOrCreate(
                    ['id_jurnal' => $jurnalSenin->id, 'id_siswa' => $sis->id],
                    [
                        'status'     => $statuses[$idx % count($statuses)],
                        'keterangan' => $statuses[$idx % count($statuses)] === 'Izin' ? 'Surat izin dari orang tua' : null,
                    ]
                );
            }
        }

        // Jurnal hari ini (Sabtu): Pemrograman Web XII RPL 1 — sudah terisi
        if ($jadwalHariIniTerisi) {
            $jurnalHariIni = Jurnal::updateOrCreate(
                ['id_jadwal' => $jadwalHariIniTerisi->id, 'tanggal' => $today],
                [
                    'materi'           => 'Implementasi REST API dengan Laravel',
                    'catatan_kejadian' => 'Praktikum berjalan lancar, siswa mengerjakan tugas deploy.',
                    'foto_kegiatan'    => null,
                    'waktu_isi'        => now()->setTime(8, 0),
                ]
            );

            $siswasRpl = Siswa::where('id_kelas', $kelas12Rpl->id)->get();
            $statusesRpl = ['Hadir', 'Hadir', 'Sakit', 'Hadir'];

            foreach ($siswasRpl as $idx => $sis) {
                AbsensiJurnal::updateOrCreate(
                    ['id_jurnal' => $jurnalHariIni->id, 'id_siswa' => $sis->id],
                    [
                        'status'     => $statusesRpl[$idx % count($statusesRpl)],
                        'keterangan' => $statusesRpl[$idx % count($statusesRpl)] === 'Sakit' ? 'Demam, surat dokter diserahkan' : null,
                    ]
                );
            }
        }
    }

    protected function hariIndonesia(): string
    {
        $map = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ];

        return $map[Carbon::now()->format('l')] ?? 'Senin';
    }
}

