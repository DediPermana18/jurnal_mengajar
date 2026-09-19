<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guru\Concerns\ResolvesTargetGuru;
use App\Models\AbsensiJurnal;
use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi Surat Dispensasi Telat (Masuk Kelas) oleh Guru Mapel.
 *
 * Alur:
 *  1. Guru mencari surat berdasarkan Kode Unik (approval_token), Nomor Surat
 *     (SIM-####/TAHUN untuk Izin Masuk Kelas / Telat, DIS-####/TAHUN untuk
 *     Dispensasi Keluar / Kegiatan), atau hasil scan QR (menampung URL
 *     .../dispen/approve/{token}).
 *  2. Sistem mengecek hak akses: hanya Guru Mapel yang mengajar pada JP
 *     (jam_masuk_jp) siswa tersebut yang berhak memverifikasi.
 *  3. Preview surat (Nama, Jam Kedatangan, Alasan, Status ACC Piket).
 *  4. Tombol "Izinkan Masuk Kelas" -> status surat menjadi "Siswa Masuk Kelas"
 *     dan presensi siswa pada Jurnal Mengajar JP tersebut otomatis menjadi
 *     "Terlambat (T)" (jika sebelumnya Alpa / belum diisi).
 */
class DispensasiVerifikasiController extends Controller
{
    use ResolvesTargetGuru;

    /**
     * Guard keanggotaan Portal Guru (sama dengan JurnalController).
     */
    protected function authorizeGuru(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->isPetugasIt() || in_array($user->effectiveRole(), ['guru_mapel', 'guru', 'wali_kelas'], true)),
            403,
            'Akses ditolak. Halaman ini khusus untuk Guru.'
        );
    }

    /**
     * Halaman cari/scan & verifikasi surat dispensasi telat (masuk kelas).
     */
    public function index(Request $request)
    {
        $this->authorizeGuru();

        // Pastikan status surat selalu segar saat dicek (auto-expired / auto-mangkir).
        DispensasiSiswa::refreshAutoExpired();
        DispensasiSiswa::refreshAutoMangkir();

        $q = trim((string) $request->query('q', ''));

        $dispen = null;
        $warning = null;
        $eval = null;

        if ($q !== '') {
            $dispen = $this->resolveDispen($q);

            if (! $dispen) {
                $warning = 'Surat dispensasi tidak ditemukan. Periksa kembali kode unik / nomor surat / hasil scan QR yang Anda masukkan.';
            } else {
                $eval = $this->evaluasiVerifikasi($dispen);
            }
        }

        return view('guru.dispensasi.verifikasi', compact('q', 'dispen', 'warning', 'eval'));
    }

    /**
     * Resolusi pencarian universal:
     * - Kode Unik (approval_token)
     * - Nomor Surat SIM-####/TAHUN (Izin Masuk) / DIS-####/TAHUN (Dispensasi Keluar)
     * - Hasil scan QR (menampung URL yang berisi ".../dispen/approve/{token}")
     */
    protected function resolveDispen(string $q): ?DispensasiSiswa
    {
        $token = $q;

        // 1. Dukungan scan QR: URL approval berisi ".../dispen/approve/{token}".
        if (preg_match('#dispen/approve/([A-Za-z0-9\-]+)#', $q, $m)) {
            $token = $m[1];
        }

        // 2. Cari via approval_token (Kode Unik) atau nomor surat (SIM-####/DIS-####/TAHUN).
        return DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver', 'catatanTerlambat'])
            ->where(function ($query) use ($q, $token) {
                $query->where('approval_token', $token)
                    ->orWhere('id', static::parseNomorSurat($q));
            })
            ->first();
    }

    protected static function parseNomorSurat(string $q): ?int
    {
        return DispensasiSiswa::parseNomorSurat($q);
    }

    protected function hariDariTanggal(Carbon $tanggal): string
    {
        $map = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        return $map[$tanggal->format('l')] ?? $tanggal->locale('id')->isoFormat('dddd');
    }

    /**
     * Evaluasi kelayakan verifikasi surat oleh guru saat ini.
     *
     * @return array{
     *     is_tipe_masuk: bool, is_hari_ini: bool, is_approved: bool,
     *     is_selesai: bool, is_ditolak: bool, jadwal: ?JadwalPelajaran,
     *     berhak: bool, jurnal: ?Jurnal, presensi: ?AbsensiJurnal
     * }
     */
    protected function evaluasiVerifikasi(DispensasiSiswa $dispen): array
    {
        $guruId = $this->effectiveGuruId();
        $siswa = $dispen->siswa;

        $isTipeMasuk = $dispen->isTipeMasuk();
        $isHariIni = $dispen->tanggal?->isToday() ?? false;
        $isApproved = $dispen->isApproved();
        $isSelesai = $dispen->isMasukKelas();
        $isDitolak = in_array($dispen->status, [
            DispensasiSiswa::STATUS_DITOLAK,
            DispensasiSiswa::STATUS_DIBATALKAN,
            DispensasiSiswa::STATUS_EXPIRED,
            DispensasiSiswa::STATUS_MANGKIR,
        ], true);

        $jadwal = null;
        $berhak = false;
        $jurnal = null;
        $presensi = null;

        if ($isTipeMasuk && $isHariIni && $isApproved && ! $isSelesai && $siswa?->id_kelas) {
            $hari = $this->hariDariTanggal($dispen->tanggal);
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $jamMasuk = (int) ($dispen->jam_masuk_jp ?? 0);

            if ($jamMasuk > 0 && $tahunAktif) {
                // Cek hak akses: guru ini mengajar pada JP masuk kelas (jam_masuk_jp)
                // kelas siswa, pada hari & tahun ajaran yang aktif.
                $jadwal = JadwalPelajaran::with(['jamPelajaran', 'mapel', 'kelas'])
                    ->where('id_guru', $guruId)
                    ->where('hari', $hari)
                    ->where('id_kelas', $siswa->id_kelas)
                    ->where('id_tahun_ajaran', $tahunAktif->id)
                    ->get()
                    ->first(fn ($j) => $j->jamPelajaran && (int) $j->jamPelajaran->jam_ke === $jamMasuk);

                $berhak = $jadwal !== null;

                if ($jadwal) {
                    $jurnal = Jurnal::where('id_jadwal', $jadwal->id)
                        ->whereDate('tanggal', $dispen->tanggal->toDateString())
                        ->first();

                    if ($jurnal) {
                        $presensi = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                            ->where('id_siswa', $dispen->id_siswa)
                            ->first();
                    }
                }
            }
        }

        return [
            'is_tipe_masuk' => $isTipeMasuk,
            'is_hari_ini' => $isHariIni,
            'is_approved' => $isApproved,
            'is_selesai' => $isSelesai,
            'is_ditolak' => $isDitolak,
            'jadwal' => $jadwal,
            'berhak' => $berhak,
            'jurnal' => $jurnal,
            'presensi' => $presensi,
        ];
    }

    /**
     * Aksi "Izinkan Masuk Kelas":
     *   a. Status surat -> "Siswa Masuk Kelas" (dengan audit verifikator).
     *   b. SEMUA presensi siswa pada jurnal mengajar tanggal surat (hari yang
     *      sama) yang masih Alpa / Hadir -> "Terlambat" (bila jurnal belum
     *      diisi, presensi akan otomatis diterapkan saat jurnal diisi).
     */
    public function izinkanMasuk(Request $request, DispensasiSiswa $dispen)
    {
        $this->authorizeGuru();
        $this->authorizeTestingMutation($dispen);

        $eval = $this->evaluasiVerifikasi($dispen);
        $backUrl = route('guru.dispensasi.verifikasi', ['q' => $dispen->approval_token]);

        if (! $eval['is_tipe_masuk'] || ! $eval['is_hari_ini'] || ! $eval['is_approved'] || $eval['is_selesai'] || $eval['is_ditolak']) {
            return redirect($backUrl)
                ->with('error', 'Surat ini tidak dapat diverifikasi masuk kelas pada saat ini, atau sudah pernah diproses.');
        }

        if (! $eval['berhak']) {
            return redirect($backUrl)
                ->with('error', 'Anda tidak mengajar pada Jam Pelajaran masuk kelas siswa tersebut, sehingga tidak berhak memverifikasi surat ini.');
        }

        $jurnalAda = $eval['jurnal'] !== null;

        $jumlahPresensi = DB::transaction(function () use ($dispen) {
            // 1. Update status surat menjadi "Siswa Masuk Kelas".
            $dispen->update([
                'status' => DispensasiSiswa::STATUS_MASUK_KELAS,
                'masuk_kelas_at' => now(),
                'masuk_kelas_by' => auth()->id(),
            ]);

            // 2. Presensi jurnal hari ini (semua jurnal mengajar tanggal surat)
            //    milik siswa -> Terlambat (untuk surat ini).
            return $dispen->terapkanMasukKelasKeAbsensi();
        });

        $pesan = 'Siswa '.($dispen->siswa?->nama ?: '-')
            .' diizinkan masuk kelas pada Jam Ke-'.($dispen->jam_masuk_jp ?? '-').'. Status surat: Siswa Masuk Kelas.';

        if ($jumlahPresensi > 0) {
            $pesan .= ' Presensi jurnal diubah menjadi Terlambat ('.$jumlahPresensi.' baris).';
        } elseif (! $jurnalAda) {
            $pesan .= ' Jurnal JP belum diisi — presensi siswa akan otomatis tercatat Terlambat saat jurnal diisi.';
        } else {
            $pesan .= ' Presensi jurnal sudah berstatus lain (Sakit/Izin/Dispen/Terlambat) sehingga tidak ditimpa.';
        }

        return redirect($backUrl)->with('success', $pesan);
    }
}