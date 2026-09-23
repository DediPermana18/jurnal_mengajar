<?php

namespace App\Http\Controllers\WakaPiket;

use App\Http\Controllers\Controller;
use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\JadwalPiket;
use App\Models\JamPelajaran;
use App\Models\Jurnal;
use App\Models\RekapPiketHarian;
use App\Models\StatusKehadiranGuru;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Portal Waka Piket.
 *
 * - Dashboard: stat kehadiran guru (Hadir/Izin/Sakit/Dinas Luar/Alpa),
 *   petugas & koordinator piket aktif hari ini, serta pantauan kelas kosong
 *   shift pagi/siang lengkap dengan tautan WhatsApp pengingat.
 * - Rekap Harian: review catatan Koordinator Shift Pagi/Siang, validasi
 *   (draft -> validated), dan isian Catatan Kejadian Luar Biasa (KLB).
 *
 * Akses dibatasi middleware 'waka-piket' (EnsureWakaPiket): admin sub_role
 * waka_piket / waka_kurikulum / null, Petugas IT + impersonasi, isPetugasIt().
 */
class WakaPiketController extends Controller
{
    /**
     * Map nama hari Inggris -> Indonesia (konsisten dengan WakaSdmController).
     */
    protected function getHariIndonesia(Carbon $date): string
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

        return $map[$date->format('l')] ?? 'Senin';
    }

    /**
     * Daftar user yang tercantum pada kolom koordinator (pagi/siang) jadwal
     * piket untuk sebuah hari. Menghormati TestingDataScope.
     *
     * @return Collection<int, User>
     */
    protected function koordinatorBertugasHariIni(string $hari, string $kolom): Collection
    {
        $ids = JadwalPiket::where('hari', $hari)
            ->pluck($kolom)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('nama')->get();
    }

    /**
     * Klasifikasi sesi ke shift 'pagi' / 'siang' berdasarkan jam mulai.
     * Sesi mulai pukul 12:00 atau sesudahnya masuk shift siang.
     */
    protected function periodeSesi(?JamPelajaran $jam): string
    {
        if (! $jam || empty($jam->jam_mulai)) {
            return 'pagi';
        }

        return Carbon::parse($jam->jam_mulai)->hour >= 12 ? 'siang' : 'pagi';
    }

    /**
     * Tautan WhatsApp pengingat Jurnal KBM ke guru pengampu (pola Waka SDM).
     */
    protected function buatWaUrl(JadwalPelajaran $jadwal): ?string
    {
        if (empty($jadwal->guru?->no_hp)) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $jadwal->guru->no_hp);
        if ($cleanPhone === '') {
            return null;
        }
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62'.substr($cleanPhone, 1);
        }

        $guruName = $jadwal->guru->nama ?? 'Bapak/Ibu Guru';
        $kelasName = $jadwal->kelas->nama_kelas ?? 'Kelas';
        $mapelName = $jadwal->mapel->nama_mapel ?? 'Mata Pelajaran';
        $jamKe = $jadwal->jamPelajaran->jam_ke ?? '-';
        $msg = "Halo {$guruName}, kami dari Tim Piket mengingatkan pengisian Jurnal KBM pada {$kelasName} - {$mapelName} (Jam ke-{$jamKe}). Terima kasih.";

        return 'https://wa.me/'.$cleanPhone.'?text='.urlencode($msg);
    }

    /**
     * 1. DASHBOARD WAKA PIKET
     */
    public function dashboard(Request $request)
    {
        $now = Carbon::now();
        $todayStr = $now->toDateString();
        $hariIniStr = $this->getHariIndonesia($now);

        // Simulasi dev (pola KurikulumDashboardController): dev_mode_senin/jumat.
        $isSimulasiSenin = $request->has('dev_mode_senin') && $request->boolean('dev_mode_senin');
        $isSimulasiJumat = $request->has('dev_mode_jumat') && $request->boolean('dev_mode_jumat');

        $hariAktif = match (true) {
            $isSimulasiSenin => 'Senin',
            $isSimulasiJumat => 'Jumat',
            default => $hariIniStr,
        };

        $tahunAktif = TahunAjaran::where('is_active', true)->first() ?? TahunAjaran::first();

        // === 1. Stat Kehadiran Guru Hari Ini (Hadir/Izin/Sakit/Dinas Luar/Alpa) ===
        $idGuruTerjadwal = JadwalPelajaran::where('hari', $hariAktif)
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->pluck('id_guru')
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id);

        $kehadiranMap = StatusKehadiranGuru::whereDate('tanggal', $todayStr)
            ->get()
            ->keyBy('user_id');

        $izinDisetujuiHariIni = IzinGuru::whereDate('tanggal', $todayStr)
            ->where('status', IzinGuru::STATUS_DISETUJUI)
            ->get()
            ->groupBy('user_id');

        $statKehadiran = [
            StatusKehadiranGuru::STATUS_HADIR => 0,
            StatusKehadiranGuru::STATUS_IZIN => 0,
            StatusKehadiranGuru::STATUS_SAKIT => 0,
            StatusKehadiranGuru::STATUS_DINAS_LUAR => 0,
            'Alpa' => 0,
        ];

        foreach ($idGuruTerjadwal as $guruId) {
            $record = $kehadiranMap->get($guruId);

            if ($record && in_array($record->status, StatusKehadiranGuru::STATUSES, true)) {
                $statKehadiran[$record->status]++;
                continue;
            }

            // Tanpa record: cek izin yang sudah disetujui pada tanggal tsb.
            $izin = $izinDisetujuiHariIni->get($guruId);
            if ($izin && $izin->isNotEmpty()) {
                $kategori = strtolower((string) $izin->first()->kategori_izin);
                if ($kategori === 'sakit') {
                    $statKehadiran[StatusKehadiranGuru::STATUS_SAKIT]++;
                } elseif (in_array($kategori, ['dinas_luar', 'tugas_luar', 'perdin'], true)) {
                    $statKehadiran[StatusKehadiranGuru::STATUS_DINAS_LUAR]++;
                } else {
                    $statKehadiran[StatusKehadiranGuru::STATUS_IZIN]++;
                }
                continue;
            }

            // Tidak ada record & tidak ada izin -> belum absen (Alpa).
            $statKehadiran['Alpa']++;
        }

        $totalGuruTerjadwal = $idGuruTerjadwal->count();
        $totalHadir = $statKehadiran[StatusKehadiranGuru::STATUS_HADIR];
        $totalTidakHadir = $statKehadiran[StatusKehadiranGuru::STATUS_IZIN]
            + $statKehadiran[StatusKehadiranGuru::STATUS_SAKIT]
            + $statKehadiran[StatusKehadiranGuru::STATUS_DINAS_LUAR]
            + $statKehadiran['Alpa'];

        // === 2. Petugas & Koordinator Piket Aktif Hari Ini ===
        $petugasPiketHariIni = JadwalPiket::getGuruPiketHariIni($now);
        $koordinatorPagi = $this->koordinatorBertugasHariIni($hariAktif, 'koordinator_pagi_user_id');
        $koordinatorSiang = $this->koordinatorBertugasHariIni($hariAktif, 'koordinator_siang_user_id');

        // === 3. Kelas Kosong Pagi / Siang (belum diisi jurnal KBM) ===
        $jadwalHariIni = JadwalPelajaran::with(['guru', 'kelas', 'mapel', 'jamPelajaran'])
            ->where('hari', $hariAktif)
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        $jurnalHariIni = Jurnal::with(['guru', 'guruPengganti'])
            ->whereDate('tanggal', $todayStr)
            ->whereIn('id_jadwal', $jadwalHariIni->pluck('id'))
            ->get()
            ->keyBy('id_jadwal');

        $daftarIzinHariIni = IzinGuru::with('user')->whereDate('tanggal', $todayStr)->get();

        $kelasKosongList = $jadwalHariIni
            ->filter(function ($jadwal) use ($jurnalHariIni) {
                $jurnal = $jurnalHariIni->get($jadwal->id);

                return ! $jurnal || empty($jurnal->materi);
            })
            ->map(function ($jadwal) use ($jurnalHariIni, $daftarIzinHariIni) {
                $jurnal = $jurnalHariIni->get($jadwal->id);
                $izin = $daftarIzinHariIni->firstWhere('user_id', $jadwal->id_guru);

                return (object) [
                    'jadwal' => $jadwal,
                    'jurnal' => $jurnal,
                    'izin' => $izin,
                    'guru' => $jadwal->guru,
                    'kelas' => $jadwal->kelas,
                    'mapel' => $jadwal->mapel,
                    'jam' => $jadwal->jamPelajaran,
                    'periode' => $this->periodeSesi($jadwal->jamPelajaran),
                    'waUrl' => $this->buatWaUrl($jadwal),
                ];
            })
            ->values();

        $sortByJam = fn (Collection $items) => $items
            ->sortBy(fn ($item) => $item->jam?->jam_ke ?? 99)
            ->values();

        $kelasKosongPagi = $sortByJam($kelasKosongList->where('periode', 'pagi'));
        $kelasKosongSiang = $sortByJam($kelasKosongList->where('periode', 'siang'));

        // === 4. Rekap Harian (untuk quick link dari dashboard) ===
        $rekapHariIni = RekapPiketHarian::whereDate('tanggal', $todayStr)->first();
        $totalRekapBelumValidasi = RekapPiketHarian::where('status', RekapPiketHarian::STATUS_DRAFT)->count();

        return view('waka-piket.dashboard', compact(
            'now',
            'todayStr',
            'hariIniStr',
            'hariAktif',
            'isSimulasiSenin',
            'isSimulasiJumat',
            'tahunAktif',
            'statKehadiran',
            'totalGuruTerjadwal',
            'totalHadir',
            'totalTidakHadir',
            'petugasPiketHariIni',
            'koordinatorPagi',
            'koordinatorSiang',
            'kelasKosongList',
            'kelasKosongPagi',
            'kelasKosongSiang',
            'rekapHariIni',
            'totalRekapBelumValidasi'
        ));
    }

    /**
     * Ambil rekap untuk sebuah tanggal, atau siapkan instance baru (belum
     * tersimpan) bila belum ada. Dijamin satu baris per tanggal (unique).
     * Logika inti di model (RekapPiketHarian::untukTanggal) — aman di SQLite
     * karena memakai whereDate() untuk kolom bertipe date.
     */
    protected function rekapUntukTanggal(string $tanggal): RekapPiketHarian
    {
        return RekapPiketHarian::untukTanggal($tanggal);
    }

    /**
     * 2. REKAP HARIAN WAKA PIKET
     *
     * Satu baris per tanggal (unique). Tampilkan detail tanggal terpilih
     * (rekapUntukTanggal) + daftar seluruh rekap untuk riwayat.
     */
    public function rekapHarian(Request $request)
    {
        $tanggal = $request->filled('tanggal')
            ? (string) $request->input('tanggal')
            : Carbon::now()->toDateString();

        try {
            Carbon::parse($tanggal);
        } catch (\Throwable $e) {
            $tanggal = Carbon::now()->toDateString();
        }

        $rekap = $this->rekapUntukTanggal($tanggal);

        if (! $rekap->exists) {
            // Pre-fill koordinator shift dari jadwal pada tanggal tsb.
            $hari = $this->getHariIndonesia(Carbon::parse($tanggal));
            $rekap->koordinator_pagi_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_pagi_user_id')->first()?->id;
            $rekap->koordinator_siang_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_siang_user_id')->first()?->id;
        }

        $daftarRekap = RekapPiketHarian::with(['koordinatorPagi', 'koordinatorSiang', 'validator'])
            ->orderBy('tanggal', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('waka-piket.rekap-harian', compact('rekap', 'daftarRekap', 'tanggal'));
    }

    /**
     * 3. VALIDASI REKAP (draft -> validated).
     *
     * Mengunci rekap tanggal tertentu: validated_by/at dicatat dari user aktif.
     * Rekap yang sudah divalidasi tidak dapat diubah lagi.
     */
    public function validasiRekap(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',
        ]);

        $rekap = $this->rekapUntukTanggal($validated['tanggal']);

        // Guard: rekap data testing hanya dapat divalidasi oleh Petugas IT / QA.
        $this->authorizeTestingMutation($rekap);

        abort_if(
            $rekap->exists && $rekap->isValidated(),
            422,
            'Rekap pada tanggal tersebut sudah tervalidasi dan tidak dapat diubah lagi.'
        );

        if (! $rekap->exists) {
            $hari = $this->getHariIndonesia(Carbon::parse($validated['tanggal']));
            $rekap->koordinator_pagi_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_pagi_user_id')->first()?->id;
            $rekap->koordinator_siang_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_siang_user_id')->first()?->id;
        }

        $rekap->fill([
            'status' => RekapPiketHarian::STATUS_VALIDATED,
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);
        $rekap->save();

        $label = now()->parse($validated['tanggal'])->translatedFormat('d F Y');

        return redirect()->route('waka-piket.rekap-harian', ['tanggal' => $validated['tanggal']])
            ->with('success', "Rekap Piket {$label} berhasil divalidasi dan dokumen terkunci.");
    }

    /**
     * 4. SIMPAN CATATAN KEJADIAN LUAR BIASA (KLB).
     *
     * Membuat rekap bila belum ada, lalu mengisi/memperbarui catatan_klb.
     * Setelah status 'validated', catatan dikunci (integritas dokumen).
     */
    public function storeCatatanKlb(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'catatan_klb' => 'required|string|max:2000',
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',
            'catatan_klb.required' => 'Catatan Kejadian Luar Biasa (KLB) wajib diisi.',
            'catatan_klb.max' => 'Catatan KLB maksimal :max karakter.',
        ]);

        $rekap = $this->rekapUntukTanggal($validated['tanggal']);

        // Guard: rekap data testing hanya dapat diubah oleh Petugas IT / QA.
        $this->authorizeTestingMutation($rekap);

        abort_if(
            $rekap->exists && $rekap->isValidated(),
            422,
            'Rekap sudah tervalidasi. Hubungi Petugas IT untuk revisi dokumen.'
        );

        if (! $rekap->exists) {
            $hari = $this->getHariIndonesia(Carbon::parse($validated['tanggal']));
            $rekap->koordinator_pagi_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_pagi_user_id')->first()?->id;
            $rekap->koordinator_siang_user_id = $this->koordinatorBertugasHariIni($hari, 'koordinator_siang_user_id')->first()?->id;
            $rekap->status = RekapPiketHarian::STATUS_DRAFT;
        }

        $rekap->catatan_klb = trim((string) $validated['catatan_klb']);
        $rekap->save();

        $label = now()->parse($validated['tanggal'])->translatedFormat('d F Y');

        return redirect()->route('waka-piket.rekap-harian', ['tanggal' => $validated['tanggal']])
            ->with('success', "Catatan Kejadian Luar Biasa (KLB) untuk {$label} berhasil disimpan.");
    }
}