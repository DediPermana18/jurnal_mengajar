<?php

namespace App\Models\Concerns;

/**
 * Penanda model yang MEWARISI konteks is_testing_data dari LINGKUNGAN AKTIF:
 *
 *   Tahun Ajaran aktif ber-is_testing_data=1  ATAU  user Petugas IT / QA Tester
 *
 * Berlaku untuk model-model sub-sistem PENJADWALAN saja (Shift, Slot Jam, Jam
 * Pulang, Plotting/Jadwal) — master data (Kelas, Tahun Ajaran, Guru, Siswa,
 * Jurusan, Mata Pelajaran, Ruangan) memakai isolasi berbasis peran. Pada model
 * berpenanda ini:
 *  - Baca:  difilter dengan WHERE is_testing_data = User::currentTestingStatus();
 *  - Tulis: is_testing_data otomatis mengikuti User::currentTestingStatus().
 *
 * Model di luar penanda ini mempertahankan isolasi lama berbasis peran user
 * (Petugas IT/QA hanya partisi testing; user lain hanya partisi real) supaya
 * tidak terjadi data isolation lockout saat Tahun Ajaran testing diaktifkan.
 */
interface TestingDataContextAware
{
}