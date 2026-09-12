<?php

namespace Database\Seeders;

use App\Models\AbsensiJurnal;
use App\Models\CatatanSiswaBermasalah;
use App\Models\CatatanTerlambat;
use App\Models\DispensasiSiswa;
use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\PenerimaTerlambat;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransaksiSeeder extends Seeder
{
    /**
     * Seed Data Transaksi & Log Operational Dummy:
     * 1. Izin Guru (Sakit, Dinas Luar, Urusan Keluarga)
     * 2. Jurnal Mengajar & Absensi Jurnal Siswa
     * 3. Presensi Siswa Harian
     * 4. Dispensasi Siswa (Keluar/Masuk)
     * 5. Catatan Terlambat Siswa (Satpam) & Penerima Notifikasi
     * 6. Catatan Siswa Bermasalah (Wali Kelas)
     */
    public function run(): void
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $guruBudi = User::where('username', 'budi.santoso')->first();
        $guruEko = User::where('username', 'eko.prasetyo')->first();
        $guruAhmad = User::where('username', 'ahmad.fauzi')->first();
        $guruPiket = User::where('username', 'siti.rahmawati')->first();
        $satpam = User::where('username', 'satpam')->first();

        // ----------------------------------------------------
        // 1. IZIN GURU
        // ----------------------------------------------------
        if ($guruBudi) {
            IzinGuru::updateOrCreate(
                ['user_id' => $guruBudi->id, 'tanggal' => $today],
                [
                    'kategori_izin' => 'dinas_luar',
                    'alasan' => 'Menghadiri Workshop Kurikulum Merdeka tingkat Provinsi.',
                    'keterangan' => 'Kegiatan di Dinas Pendidikan Provinsi Jawa Tengah.',
                    'tugas_siswa' => 'Mengerjakan tugas praktikum halaman 45-50.',
                    'status' => IzinGuru::STATUS_DISETUJUI,
                    'approved_by_piket' => $guruPiket?->id,
                    'approved_at' => now(),
                ]
            );
        }

        if ($guruEko) {
            IzinGuru::updateOrCreate(
                ['user_id' => $guruEko->id, 'tanggal' => $yesterday],
                [
                    'kategori_izin' => 'sakit',
                    'alasan' => 'Demam tinggi dan flu berat.',
                    'keterangan' => 'Istirahat sesuai anjuran dokter.',
                    'tugas_siswa' => 'Studi literatur modul Desain Grafis.',
                    'status' => IzinGuru::STATUS_PENDING_WAKA,
                ]
            );
        }

        $this->command->info(' [TransaksiSeeder] Sample Izin Guru berhasil dibuat.');

        // ----------------------------------------------------
        // 2. JURNAL & ABSENSI JURNAL
        // ----------------------------------------------------
        $jadwalList = JadwalPelajaran::with(['kelas', 'guru'])->limit(3)->get();

        foreach ($jadwalList as $jadwal) {
            $jurnal = Jurnal::updateOrCreate(
                ['id_jadwal' => $jadwal->id, 'tanggal' => $today],
                [
                    'id_guru' => $jadwal->id_guru,
                    'status_kehadiran' => 'Hadir',
                    'materi' => 'Pendalaman Konsep dan Diskusi Kelompok',
                    'catatan_kejadian' => 'Siswa mengikuti KBM dengan kondusif dan aktif.',
                    'waktu_isi' => now()->setTime(8, 15),
                ]
            );

            // Simpan absensi siswa untuk jurnal ini
            $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)->get();
            $statuses = ['Hadir', 'Hadir', 'Hadir', 'Hadir', 'Sakit', 'Izin'];

            foreach ($siswas as $idx => $sis) {
                $st = $statuses[$idx % count($statuses)];
                AbsensiJurnal::updateOrCreate(
                    ['id_jurnal' => $jurnal->id, 'id_siswa' => $sis->id],
                    [
                        'status' => $st,
                        'keterangan' => $st === 'Hadir' ? null : "Keterangan status {$st}",
                    ]
                );
            }
        }

        $this->command->info(' [TransaksiSeeder] Sample Jurnal Mengajar & Absensi Jurnal berhasil dibuat.');

        // ----------------------------------------------------
        // 3. PRESENSI SISWA HARIAN
        // ----------------------------------------------------
        $siswaAll = Siswa::limit(15)->get();
        foreach ($siswaAll as $idx => $sis) {
            $st = ($idx % 5 === 0) ? 'Izin' : 'Hadir';
            PresensiSiswa::updateOrCreate(
                ['id_siswa' => $sis->id, 'tanggal' => $today],
                [
                    'id_kelas' => $sis->id_kelas,
                    'status' => $st,
                    'keterangan' => $st === 'Hadir' ? null : 'Izin acara keluarga',
                    'id_guru_piket' => $guruPiket?->id,
                ]
            );
        }

        $this->command->info(' [TransaksiSeeder] Sample Presensi Siswa Harian berhasil dibuat.');

        // ----------------------------------------------------
        // 4. DISPENSASI SISWA
        // ----------------------------------------------------
        $siswaDispen = Siswa::first();
        if ($siswaDispen && $guruPiket) {
            DispensasiSiswa::updateOrCreate(
                ['id_siswa' => $siswaDispen->id, 'tanggal' => $today],
                [
                    'id_guru_piket' => $guruPiket->id,
                    'jenis' => DispensasiSiswa::JENIS_ACARA,
                    'tipe_dispen' => DispensasiSiswa::TIPE_KELUAR,
                    'jam_ke' => '3,4',
                    'alasan' => 'Mewakili Sekolah dalam Lomba FLS2N Tingkat Kota.',
                    'status' => DispensasiSiswa::STATUS_DISETUJUI,
                    'approved_at' => now(),
                    'approved_by' => $guruPiket->id,
                ]
            );
        }

        $this->command->info(' [TransaksiSeeder] Sample Dispensasi Siswa berhasil dibuat.');

        // ----------------------------------------------------
        // 5. CATATAN TERLAMBAT (SATPAM)
        // ----------------------------------------------------
        $siswaTelat = Siswa::skip(1)->first();
        if ($siswaTelat && $satpam) {
            $catatTelat = CatatanTerlambat::updateOrCreate(
                ['id_siswa' => $siswaTelat->id, 'tanggal' => $today],
                [
                    'jam_masuk' => now()->setTime(7, 40),
                    'keterangan' => 'Ban sepeda motor bocor di jalan.',
                    'id_satpam' => $satpam->id,
                ]
            );

            // Forward notification log to Guru Piket & Wali Kelas
            if ($guruPiket) {
                PenerimaTerlambat::updateOrCreate(
                    ['catatan_terlambat_id' => $catatTelat->id, 'user_id' => $guruPiket->id],
                    ['peran' => PenerimaTerlambat::PERAN_GURU_PIKET]
                );
            }

            // Find Wali Kelas
            $wali = User::where('id', $siswaTelat->kelas?->id_wali_kelas)->first();
            if ($wali) {
                PenerimaTerlambat::updateOrCreate(
                    ['catatan_terlambat_id' => $catatTelat->id, 'user_id' => $wali->id],
                    ['peran' => PenerimaTerlambat::PERAN_WALI_KELAS]
                );
            }
        }

        $this->command->info(' [TransaksiSeeder] Sample Catatan Terlambat Siswa & Penerima Notifikasi berhasil dibuat.');

        // ----------------------------------------------------
        // 6. CATATAN SISWA BERMASALAH (WALI KELAS)
        // ----------------------------------------------------
        $siswaBermasalah = Siswa::skip(2)->first();
        if ($siswaBermasalah) {
            $waliKelas = User::find($siswaBermasalah->kelas?->id_wali_kelas) ?? $guruBudi;

            if ($waliKelas) {
                CatatanSiswaBermasalah::updateOrCreate(
                    ['id_siswa' => $siswaBermasalah->id, 'id_wali_kelas' => $waliKelas->id],
                    [
                        'jenis_tindakan' => CatatanSiswaBermasalah::JENIS_PANGGIL_ORTU,
                        'catatan' => 'Keterlambatan berturut-turut 3 hari dan tidak mengumpulkan tugas.',
                        'status' => CatatanSiswaBermasalah::STATUS_DIPANGGIL,
                    ]
                );
            }
        }

        $this->command->info(' [TransaksiSeeder] Sample Catatan Siswa Bermasalah berhasil dibuat.');
    }
}
