<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use App\Models\IzinGuru;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GuruPiketController extends Controller
{
    /**
     * Akses ditentukan oleh jadwal_piket pada hari berjalan, bukan role user.
     */
    protected function authorizeGuruPiket()
    {
        $user = Auth::user();
        abort_unless(
            $user instanceof User
                && ($user->isPetugasIt() || $user->activeRole() === 'guru_piket' || $user->isPiketHariIni()),
            403,
            'Akses ditolak. Anda tidak mendapat jadwal piket hari ini.'
        );
    }

    /**
     * Dashboard Guru Piket
     */
    public function dashboard()
    {
        $this->authorizeGuruPiket();

        $today = now()->toDateString();
        $hariIni = now()->translatedFormat('l');

        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        // 1. Total siswa tidak hadir (Sakit / Izin / Alpha) hari ini
        $siswaTidakHadir = PresensiSiswa::whereDate('tanggal', $today)
            ->whereIn('status', ['Sakit', 'Izin', 'Alpha'])
            ->count();

        // 2. Guru tidak hadir / mengajukan izin hari ini
        $guruIzinHariIni = IzinGuru::whereDate('tanggal', $today)->count();

        // 3. Total dispensasi keluar / masuk hari ini
        $dispensasiHariIni = DispensasiSiswa::whereDate('tanggal', $today)->count();

        // 4. Kelas dengan KBM belum terisi (ada sesi KBM hari ini yang jurnalnya belum diisi)
        $jadwalHariIni = JadwalPelajaran::where('hari', $hariIni)
            ->when($tahunAktif, fn ($q) => $q->where('id_tahun_ajaran', $tahunAktif->id))
            ->get();

        $idJadwalHariIni = $jadwalHariIni->pluck('id');
        $jurnalTerisiIds = $idJadwalHariIni->isEmpty()
            ? collect()
            : Jurnal::whereDate('tanggal', $today)
                ->whereIn('id_jadwal', $idJadwalHariIni)
                ->whereNotNull('materi')
                ->where('materi', '!=', '')
                ->pluck('id_jadwal')
                ->unique();

        $kelasKbmBelumTerisi = $jadwalHariIni
            ->groupBy('id_kelas')
            ->filter(fn ($sessions) => $sessions->contains(fn ($s) => ! $jurnalTerisiIds->contains($s->id)))
            ->count();

        // Recent: 5 dispensasi terbaru hari ini
        $dispensasiTerbaru = DispensasiSiswa::with(['siswa.kelas'])
            ->whereDate('tanggal', $today)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        // Recent: daftar guru izin hari ini
        $izinGuruHariIni = IzinGuru::with('user')
            ->whereDate('tanggal', $today)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $izinPendingPiket = IzinGuru::where('status', IzinGuru::STATUS_PENDING_PIKET)->count();

        return view('piket.dashboard', compact(
            'today',
            'hariIni',
            'siswaTidakHadir',
            'guruIzinHariIni',
            'dispensasiHariIni',
            'kelasKbmBelumTerisi',
            'dispensasiTerbaru',
            'izinGuruHariIni',
            'izinPendingPiket',
        ));
    }

    /**
     * Presensi Guru Harian
     */
    public function presensiGuru()
    {
        $this->authorizeGuruPiket();

        return view('piket.presensi_guru');
    }

    /**
     * Jurnal KBM Harian — daftar jurnal untuk semua kelas hari ini
     */
    public function jurnalKBM(Request $request)
    {
        $this->authorizeGuruPiket();

        $today = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);

        $dataJurnal = Jurnal::with([
            'guru',
            'guruPengganti',
            'jadwal.guru',
            'jadwal.mapel',
            'jadwal.kelas',
            'jadwal.jamPelajaran',
        ])
            ->whereDate('tanggal', $tanggal)
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($jurnal) use ($today) {
                // Tambah flag editable: hanya bisa edit jika tanggal jurnal = hari ini
                $jurnal->is_editable = $jurnal->tanggal?->toDateString() === $today;

                return $jurnal;
            })
            ->sortBy(function ($jurnal) {
                return $jurnal->jadwal?->jamPelajaran?->jam_mulai ?? '99:99:99';
            })
            ->values();

        $dataJurnal = $this->groupJurnalBerurutan($dataJurnal);

        $gurus = User::orderBy('nama')->get();

        return view('piket.jurnal', compact('dataJurnal', 'tanggal', 'today', 'gurus'));
    }

    /**
     * Gabungkan jurnal yang identitasnya sama dan jamnya bersambung tepat.
     */
    protected function groupJurnalBerurutan($jurnals)
    {
        return $jurnals->reduce(function ($groups, $jurnal) {
            $jam = $jurnal->jadwal?->jamPelajaran;
            $guruId = $jurnal->id_guru ?? $jurnal->jadwal?->id_guru;
            $kelasId = $jurnal->jadwal?->id_kelas;
            $mapelId = $jurnal->jadwal?->id_mapel;

            if (! $jam || ! $jurnal->tanggal || $guruId === null || $kelasId === null || $mapelId === null) {
                $jurnal->display_jam_mulai = $jam?->jam_mulai;
                $jurnal->display_jam_selesai = $jam?->jam_selesai;
                $jurnal->display_materi = $jurnal->materi;
                $jurnal->display_catatan = $jurnal->catatan_kejadian;

                return $groups->push($jurnal);
            }

            $last = $groups->last();
            $lastJamSelesai = $last?->display_jam_selesai;
            $lastKey = $last?->group_key;
            $key = implode('|', [$jurnal->tanggal->toDateString(), $guruId, $kelasId, $mapelId]);

            if ($last && $lastKey === $key && $lastJamSelesai === $jam->jam_mulai) {
                $last->display_jam_selesai = $jam->jam_selesai;
                $last->display_materi = $this->gabungkanTeks($last->display_materi, $jurnal->materi);
                $last->display_catatan = $this->gabungkanTeks($last->display_catatan, $jurnal->catatan_kejadian);

                return $groups;
            }

            $jurnal->group_key = $key;
            $jurnal->display_jam_mulai = $jam->jam_mulai;
            $jurnal->display_jam_selesai = $jam->jam_selesai;
            $jurnal->display_materi = $jurnal->materi;
            $jurnal->display_catatan = $jurnal->catatan_kejadian;

            return $groups->push($jurnal);
        }, collect());
    }

    protected function gabungkanTeks(?string $sebelumnya, ?string $berikutnya): ?string
    {
        return collect([$sebelumnya, $berikutnya])
            ->filter(fn ($teks) => filled(trim($teks)))
            ->unique()
            ->implode(' | ') ?: null;
    }

    /**
     * Presensi Siswa Harian - Tampilkan form input presensi
     */
    public function presensiSiswa(Request $request)
    {
        $this->authorizeGuruPiket();

        $today = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);
        $idKelas = $request->get('id_kelas');

        // Ambil daftar kelas untuk dropdown filter
        $kelasList = Kelas::orderBy('nama_kelas')->get();

        // Ambil siswa berdasarkan kelas yang dipilih
        $siswaQuery = Siswa::with('kelas')
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama');

        if ($idKelas) {
            $siswaQuery->where('id_kelas', $idKelas);
        }

        $dataSiswa = $siswaQuery->get();

        // Ambil presensi yang sudah ada untuk tanggal & kelas tsb
        $presensiExisting = collect();
        if ($idKelas) {
            $presensiExisting = PresensiSiswa::where('tanggal', $tanggal)
                ->where('id_kelas', $idKelas)
                ->get()
                ->keyBy('id_siswa');
        }

        return view('piket.presensi_siswa', compact(
            'kelasList',
            'dataSiswa',
            'presensiExisting',
            'tanggal',
            'today',
            'idKelas'
        ));
    }

    /**
     * Simpan Presensi Siswa Harian
     */
    public function storePresensiSiswa(Request $request)
    {
        $this->authorizeGuruPiket();

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'id_kelas' => 'required|exists:kelas,id',
            'presensi' => 'required|array',
            'presensi.*.id_siswa' => 'required|exists:siswa,id',
            'presensi.*.status' => 'required|in:Hadir,Sakit,Izin',
            'presensi.*.keterangan' => 'nullable|string|max:255',
            'presensi.*.foto_surat' => 'nullable|file|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $user = Auth::user();

        $siswaIds = collect($validated['presensi'])->pluck('id_siswa')->unique()->values()->all();
        $siswaMap = $siswaIds ? Siswa::whereIn('id', $siswaIds)->get()->keyBy('id') : collect();

        // Prefix file: SRT_{KELAS}_{NIS}_{TANGGAL}_{hash}
        $kelas = Kelas::find($validated['id_kelas']);
        $namaKelas = strtoupper(preg_replace('/[\s-]+/', '-', trim(preg_replace('/[^a-zA-Z0-9\s-]/', '', (string) ($kelas?->nama_kelas ?? 'KELAS'))))) ?: 'KELAS';
        $tglStr = str_replace('-', '', (string) $validated['tanggal']);

        foreach ($validated['presensi'] as $item) {
            // Hindari galat unique [id_siswa, tanggal] saat baris lama ter-soft delete:
            // temukan termasuk baris trashed, lalu restore & perbarui baris yang sama.
            $presensi = PresensiSiswa::withTrashed()->firstOrNew([
                'id_siswa' => $item['id_siswa'],
                'tanggal' => $validated['tanggal'],
            ]);

            // Guard: presensi data testing hanya dapat diubah oleh IT/QA.
            if ($presensi->exists) {
                $this->authorizeTestingMutation($presensi);
            }

            if ($presensi->trashed()) {
                $presensi->restore();
            }

            // Upload foto surat (opsional) jika status Sakit/Izin.
            $fotoSuratPath = null;
            $fileSurat = $request->file("presensi.{$item['id_siswa']}.foto_surat");
            if ($fileSurat && $fileSurat->isValid() && in_array($item['status'], ['Sakit', 'Izin'], true)) {
                $siswa = $siswaMap->get($item['id_siswa']);
                $ext = strtolower($fileSurat->getClientOriginalExtension()) ?: 'jpg';
                if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                    $ext = 'jpg';
                }
                $nis = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($siswa?->nis ?? $siswa?->nisn ?? $item['id_siswa'])) ?: (string) $item['id_siswa'];
                $hash = substr(md5(uniqid((string) time(), true)), 0, 6);
                $filename = "SRT_{$namaKelas}_{$nis}_{$tglStr}_{$hash}.{$ext}";
                $fotoSuratPath = $fileSurat->storeAs('foto_surat', $filename, 'public');

                // Hapus file lama bila diganti dengan file baru.
                if ($presensi->exists && $presensi->foto_surat && $presensi->foto_surat !== $fotoSuratPath) {
                    foreach (['public', 'local'] as $disk) {
                        if (Storage::disk($disk)->exists($presensi->foto_surat)) {
                            Storage::disk($disk)->delete($presensi->foto_surat);
                            break;
                        }
                    }
                }
            }

            $presensi->fill([
                'id_kelas' => $validated['id_kelas'],
                'status' => $item['status'],
                'keterangan' => $item['keterangan'] ?? null,
                'foto_surat' => $fotoSuratPath ?? $presensi->foto_surat,
                'id_guru_piket' => $user->id,
            ])->save();
        }

        return redirect()->route('piket.presensi-siswa', [
            'tanggal' => $validated['tanggal'],
            'id_kelas' => $validated['id_kelas'],
        ])->with('success', 'Presensi siswa berhasil disimpan.');
    }
}
