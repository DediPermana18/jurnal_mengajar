<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\AbsensiJurnal;
use App\Models\JadwalPelajaran;
use App\Models\Jurnal;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class JurnalController extends Controller
{
    protected function authorizeGuru(): void
    {
        $role = auth()->check() ? auth()->user()->role : null;
        abort_unless(
            in_array($role, ['guru_mapel', 'guru', 'wali_kelas']),
            403,
            'Akses ditolak. Halaman ini khusus untuk Guru.'
        );
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

        return $map[Carbon::now()->format('l')] ?? Carbon::now()->locale('id')->isoFormat('dddd');
    }

    protected function formatWaktu(?string $jamMulai, ?string $jamSelesai): string
    {
        if (!$jamMulai || !$jamSelesai) {
            return '-';
        }

        $mulai = Carbon::parse($jamMulai)->format('H.i');
        $selesai = Carbon::parse($jamSelesai)->format('H.i');

        return "{$mulai} - {$selesai}";
    }

    /**
     * Helper: Sanitisasi string untuk nama file (hapus karakter aneh, ubah spasi ke dash)
     */
    protected function sanitizeString(?string $string): string
    {
        if (!$string) {
            return 'UNKNOWN';
        }
        $sanitized = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
        $sanitized = preg_replace('/[\s-]+/', '-', trim($sanitized));
        return strtoupper($sanitized) ?: 'UNKNOWN';
    }

    /**
     * Helper: Simpan file dari Base64 DataURL (Kamera Live) atau File Upload biasa dengan format nama terstruktur
     */
    protected function saveBase64OrFile(Request $request, string $base64Key, string $fileKey, string $folder, ?string $customPrefix = null): ?string
    {
        $base64Data = $request->input($base64Key);
        $hash = substr(md5(uniqid((string) time(), true)), 0, 6);

        if ($base64Data && preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
                $nameOnly = $customPrefix ? "{$customPrefix}_{$hash}.{$ext}" : uniqid() . '_' . time() . '.' . $ext;
                $filename = $folder . '/' . $nameOnly;
                Storage::disk('local')->put($filename, $data);
                return $filename;
            }
        }

        if ($request->hasFile($fileKey)) {
            $file = $request->file($fileKey);
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $nameOnly = $customPrefix ? "{$customPrefix}_{$hash}.{$ext}" : uniqid() . '_' . time() . '.' . $ext;
            return $file->storeAs($folder, $nameOnly, 'local');
        }

        return null;
    }

    /**
     * Evaluasi hak akses & status locking per jadwal (Mode Produksi Normal).
     */
    protected function evaluateJadwal(JadwalPelajaran $jadwal, ?Jurnal $jurnalHariIni = null): array
    {
        $user  = auth()->user();
        $now   = Carbon::now();
        $today = Carbon::today()->toDateString();

        $isOwner = (int) $jadwal->id_guru === (int) $user->id;
        $jam     = $jadwal->jamPelajaran;

        $jamMulai   = ($jam && $jam->jam_mulai) ? Carbon::parse($today . ' ' . $jam->jam_mulai) : null;
        $jamSelesai = ($jam && $jam->jam_selesai) ? Carbon::parse($today . ' ' . $jam->jam_selesai) : null;

        $jurnal   = $jurnalHariIni ?? Jurnal::where('id_jadwal', $jadwal->id)
            ->whereDate('tanggal', $today)
            ->first();
        $isFilled = $jurnal !== null;

        // Mode Produksi Normal: Pengisian hanya bisa dilakukan jika waktu real-time sudah melewati jam_mulai
        $isTimeReached = $jamMulai ? $now->gte($jamMulai) : true;
        $canFill       = $isOwner && $isTimeReached && !$isFilled;

        // Cek tanggal jurnal: Hari Ini vs Tanggal Lalu
        $jurnalTanggal = $jurnal && $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : $today;
        $isToday       = $jurnalTanggal === $today;
        $canEdit       = $isFilled && $isOwner && $isToday;

        $lockReason = null;
        if (!$isOwner) {
            $lockReason = 'Bukan jadwal mengajar Anda';
        } elseif ($isFilled && !$isToday) {
            $lockReason = 'Jurnal tanggal lalu terkunci (Read-Only)';
        } elseif ($isFilled) {
            $lockReason = 'Jurnal sudah diisi hari ini';
        } elseif (!$isTimeReached) {
            $mulaiStr   = $jamMulai ? $jamMulai->format('H:i') : '-';
            $lockReason = "Belum waktunya jam pelajaran (mulai pukul {$mulaiStr})";
        }

        return [
            'is_owner'        => $isOwner,
            'is_time_reached' => $isTimeReached,
            'is_filled'       => $isFilled,
            'is_today'        => $isToday,
            'can_fill'        => $canFill,
            'can_edit'        => $canEdit,
            'lock_reason'     => $lockReason,
            'jurnal'          => $jurnal,
            'waktu'           => $this->formatWaktu($jam?->jam_mulai, $jam?->jam_selesai),
        ];
    }

    /**
     * Halaman utama — daftar jadwal mengajar hari ini.
     */
    public function index()
    {
        $this->authorizeGuru();

        $user = auth()->user();
        $hari = $this->hariIndonesia();
        $today = Carbon::today()->toDateString();

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        $query = JadwalPelajaran::with(['jamPelajaran', 'kelas', 'mapel'])
            ->where('id_guru', $user->id)
            ->where('hari', $hari);

        if ($tahunAktif) {
            $query->where('id_tahun_ajaran', $tahunAktif->id);
        }

        $jadwalIds = (clone $query)->pluck('id');

        $jurnalHariIni = Jurnal::whereIn('id_jadwal', $jadwalIds)
            ->whereDate('tanggal', $today)
            ->get()
            ->keyBy('id_jadwal');

        $jadwals = $query
            ->get()
            ->sortBy(fn ($j) => $j->jamPelajaran?->jam_ke ?? 999)
            ->values()
            ->map(function ($jadwal) use ($jurnalHariIni) {
                $eval = $this->evaluateJadwal($jadwal, $jurnalHariIni->get($jadwal->id));

                return (object) [
                    'jadwal'      => $jadwal,
                    'jam_ke'      => $jadwal->jamPelajaran?->jam_ke ?? '-',
                    'waktu'       => $eval['waktu'],
                    'kelas'       => $jadwal->kelas?->nama_kelas ?? '-',
                    'mapel'       => $jadwal->mapel?->nama_mapel ?? '-',
                    'is_filled'   => $eval['is_filled'],
                    'is_today'    => $eval['is_today'],
                    'can_fill'    => $eval['can_fill'],
                    'can_edit'    => $eval['can_edit'],
                    'lock_reason' => $eval['lock_reason'],
                    'jurnal'      => $eval['jurnal'],
                ];
            });

        return view('guru.jurnal.index', compact('jadwals', 'hari', 'today'));
    }

    /**
     * Form pengisian jurnal & presensi baru.
     */
    public function create(JadwalPelajaran $jadwal)
    {
        $this->authorizeGuru();

        $jadwal->load(['jamPelajaran', 'kelas', 'mapel']);

        $eval = $this->evaluateJadwal($jadwal);

        if (!$eval['can_fill']) {
            return redirect()
                ->route('guru.jurnal')
                ->with('error', $eval['lock_reason'] ?? 'Jadwal ini tidak dapat diisi saat ini.');
        }

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        $today = Carbon::today()->toDateString();
        $waktu = $eval['waktu'];

        return view('guru.jurnal.form', compact('jadwal', 'siswas', 'today', 'waktu'));
    }

    /**
     * Simpan jurnal & absensi siswa baru.
     */
    public function store(Request $request)
    {
        $this->authorizeGuru();

        $validated = $request->validate([
            'id_jadwal'        => 'required|exists:jadwal_pelajaran,id',
            'materi'           => 'required|string',
            'catatan_kejadian' => 'nullable|string',
            'tidak_hadir'      => 'nullable|array',
            'presensi'         => 'nullable|array',
            'status'           => 'nullable|array',
            'status.*'         => 'in:Sakit,Izin,Alpa,Dispen',
            'keterangan'       => 'nullable|array',
            'keterangan.*'     => 'nullable|string|max:500',
        ]);

        $jadwal = JadwalPelajaran::with(['kelas', 'mapel', 'jamPelajaran'])->findOrFail($validated['id_jadwal']);
        $eval = $this->evaluateJadwal($jadwal);

        if (!$eval['can_fill']) {
            return redirect()
                ->route('guru.jurnal')
                ->with('error', $eval['lock_reason'] ?? 'Jadwal ini tidak dapat diisi saat ini.');
        }

        $tidakHadirIds = collect($validated['tidak_hadir'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $presensiInput = $request->input('presensi', []);
        $statusMap     = $request->input('status', []);
        $keteranganMap = $request->input('keterangan', []);

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->get();

        DB::transaction(function () use ($validated, $jadwal, $tidakHadirIds, $presensiInput, $statusMap, $keteranganMap, $request, $siswas) {
            $namaKelas = $this->sanitizeString($jadwal->kelas?->nama_kelas);
            $guruIdNip = auth()->user()?->nip ?? $jadwal->guru?->nip ?? $jadwal->id_guru ?? auth()->id();
            $tglStr    = Carbon::today()->format('Ymd');
            $jamKe     = $jadwal->jamPelajaran?->jam_ke ?? 1;

            $prefixJurnal = "JRN_{$namaKelas}_{$guruIdNip}_{$tglStr}_{$jamKe}";
            $fotoKegiatanPath = $this->saveBase64OrFile($request, 'foto_kegiatan_camera', 'foto_kegiatan', 'foto_jurnal', $prefixJurnal);

            // Cari semua jadwal dalam satu kelompok/sesi (group_id)
            if ($jadwal->group_id) {
                $groupSchedules = JadwalPelajaran::where('group_id', $jadwal->group_id)->get();
            } else {
                $groupSchedules = collect([$jadwal]);
            }

            $todayDate = Carbon::today()->toDateString();
            $loggedGuruId = auth()->id() ?? $jadwal->id_guru;

            foreach ($groupSchedules as $sched) {
                $existingJurnal = Jurnal::where('id_jadwal', $sched->id)
                    ->whereDate('tanggal', $todayDate)
                    ->first();

                if ($existingJurnal) {
                    continue;
                }

                $jurnal = Jurnal::create([
                    'id_jadwal'        => $sched->id,
                    'id_guru'          => $sched->id_guru ?? $loggedGuruId,
                    'id_guru_pengganti' => null,
                    'status_kehadiran'  => 'Hadir',
                    'tanggal'          => $todayDate,
                    'materi'           => $validated['materi'],
                    'catatan_kejadian' => $validated['catatan_kejadian'] ?? null,
                    'foto_kegiatan'    => $fotoKegiatanPath,
                    'waktu_isi'        => now(),
                ]);

                $idJurnal = $jurnal->id;

                foreach ($siswas as $siswa) {
                    $pData = $presensiInput[$siswa->id] ?? [];
                    $isTidakHadir = $tidakHadirIds->contains($siswa->id) || isset($pData['status']);

                    $status     = $isTidakHadir ? ($pData['status'] ?? $statusMap[$siswa->id] ?? 'Sakit') : 'Hadir';
                    $keterangan = $isTidakHadir ? ($pData['keterangan'] ?? $keteranganMap[$siswa->id] ?? null) : null;
                    $fotoSurat  = null;

                    if ($isTidakHadir) {
                        $siswaNisId  = $siswa->nis ?? $siswa->id;
                        $prefixSurat = "SRT_{$namaKelas}_{$siswaNisId}_{$tglStr}";

                        $fotoSurat = $this->saveBase64OrFile(
                            $request,
                            "presensi.{$siswa->id}.foto_surat_camera",
                            "presensi.{$siswa->id}.foto_surat",
                            'foto_surat',
                            $prefixSurat
                        );
                        if (!$fotoSurat) {
                            $fotoSurat = $this->saveBase64OrFile(
                                $request,
                                "foto_surat_camera.{$siswa->id}",
                                "foto_surat.{$siswa->id}",
                                'foto_surat',
                                $prefixSurat
                            );
                        }
                    }

                    AbsensiJurnal::create([
                        'id_jurnal'  => $idJurnal,
                        'id_siswa'   => $siswa->id,
                        'status'     => $status,
                        'keterangan' => $keterangan,
                        'foto_surat' => $fotoSurat,
                    ]);
                }
            }
        });

        return redirect()
            ->route('guru.jurnal')
            ->with('success', 'Jurnal mengajar dan presensi siswa berhasil disimpan untuk sesi pelajaran ini!');
    }

    /**
     * Tampilkan detail jurnal secara Read-Only.
     */
    public function show(Jurnal $jurnal)
    {
        $this->authorizeGuru();

        $jurnal->load(['jadwal.jamPelajaran', 'jadwal.kelas', 'jadwal.mapel', 'absensiJurnal.siswa']);

        $jadwal = $jurnal->jadwal;
        $eval   = $this->evaluateJadwal($jadwal, $jurnal);

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        $today      = $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : Carbon::today()->toDateString();
        $waktu      = $eval['waktu'];
        $absensiMap = $jurnal->absensiJurnal->keyBy('id_siswa');

        return view('guru.jurnal.show', compact('jurnal', 'jadwal', 'siswas', 'today', 'waktu', 'absensiMap'));
    }

    /**
     * Form edit untuk jurnal yang diisi HARI INI.
     */
    public function edit(Jurnal $jurnal)
    {
        $this->authorizeGuru();

        $today = Carbon::today()->toDateString();
        $jurnalTanggal = $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : null;

        // Kunci edit jika jurnal berasal dari tanggal lalu (Read-Only)
        if ($jurnalTanggal && $jurnalTanggal < $today) {
            return redirect()
                ->route('guru.jurnal.show', $jurnal->id)
                ->with('error', 'Jurnal pada tanggal lalu sudah terkunci dan hanya dapat dilihat (Read-Only).');
        }

        $jurnal->load(['jadwal.jamPelajaran', 'jadwal.kelas', 'jadwal.mapel', 'absensiJurnal']);

        $jadwal = $jurnal->jadwal;
        $eval   = $this->evaluateJadwal($jadwal, $jurnal);

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        $waktu      = $eval['waktu'];
        $absensiMap = $jurnal->absensiJurnal->keyBy('id_siswa');

        return view('guru.jurnal.form', compact('jurnal', 'jadwal', 'siswas', 'today', 'waktu', 'absensiMap'));
    }

    /**
     * Update jurnal & absensi siswa HARI INI.
     */
    public function update(Request $request, Jurnal $jurnal)
    {
        $this->authorizeGuru();

        $today = Carbon::today()->toDateString();
        $jurnalTanggal = $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : null;

        if ($jurnalTanggal && $jurnalTanggal < $today) {
            abort(403, 'Jurnal pada tanggal lalu sudah terkunci dan tidak dapat diubah.');
        }

        $validated = $request->validate([
            'materi'           => 'required|string',
            'catatan_kejadian' => 'nullable|string',
            'tidak_hadir'      => 'nullable|array',
            'presensi'         => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $validated, $jurnal) {
            $jadwal    = $jurnal->jadwal;
            $namaKelas = $this->sanitizeString($jadwal->kelas?->nama_kelas);
            $guruIdNip = auth()->user()?->nip ?? $jadwal->guru?->nip ?? $jadwal->id_guru ?? auth()->id();
            $tglStr    = Carbon::parse($jurnal->tanggal)->format('Ymd');
            $jamKe     = $jadwal->jamPelajaran?->jam_ke ?? 1;

            $prefixJurnal = "JRN_{$namaKelas}_{$guruIdNip}_{$tglStr}_{$jamKe}";

            $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
                ->where('status_siswa', 'Aktif')
                ->get();

            $newFotoKegiatan = $this->saveBase64OrFile($request, 'foto_kegiatan_camera', 'foto_kegiatan', 'foto_jurnal', $prefixJurnal);
            if ($newFotoKegiatan) {
                if ($jurnal->foto_kegiatan) {
                    if (Storage::disk('local')->exists($jurnal->foto_kegiatan)) {
                        Storage::disk('local')->delete($jurnal->foto_kegiatan);
                    } elseif (Storage::disk('public')->exists($jurnal->foto_kegiatan)) {
                        Storage::disk('public')->delete($jurnal->foto_kegiatan);
                    }
                }
                $jurnal->foto_kegiatan = $newFotoKegiatan;
            }

            $jurnal->materi           = $validated['materi'];
            $jurnal->catatan_kejadian = $validated['catatan_kejadian'] ?? null;
            $jurnal->save();

            $tidakHadirIds = collect($request->input('tidak_hadir', []))->map(fn ($id) => (int) $id);
            $presensiInput = $request->input('presensi', []);

            foreach ($siswas as $siswa) {
                $pData = $presensiInput[$siswa->id] ?? [];
                $isTidakHadir = $tidakHadirIds->contains($siswa->id) || isset($pData['status']);

                $status     = $isTidakHadir ? ($pData['status'] ?? $request->input("status.{$siswa->id}", 'Sakit')) : 'Hadir';
                $keterangan = $isTidakHadir ? ($pData['keterangan'] ?? $request->input("keterangan.{$siswa->id}")) : null;

                $fotoSurat = null;
                if ($isTidakHadir) {
                    $siswaNisId  = $siswa->nis ?? $siswa->id;
                    $prefixSurat = "SRT_{$namaKelas}_{$siswaNisId}_{$tglStr}";

                    $fotoSurat = $this->saveBase64OrFile(
                        $request,
                        "presensi.{$siswa->id}.foto_surat_camera",
                        "presensi.{$siswa->id}.foto_surat",
                        'foto_surat',
                        $prefixSurat
                    );
                    if (!$fotoSurat) {
                        $fotoSurat = $this->saveBase64OrFile(
                            $request,
                            "foto_surat_camera.{$siswa->id}",
                            "foto_surat.{$siswa->id}",
                            'foto_surat',
                            $prefixSurat
                        );
                    }
                }

                $existingAbsensi = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                    ->where('id_siswa', $siswa->id)
                    ->first();

                if ($existingAbsensi) {
                    $updateData = [
                        'status'     => $status,
                        'keterangan' => $keterangan,
                    ];
                    if ($fotoSurat) {
                        if ($existingAbsensi->foto_surat) {
                            if (Storage::disk('local')->exists($existingAbsensi->foto_surat)) {
                                Storage::disk('local')->delete($existingAbsensi->foto_surat);
                            } elseif (Storage::disk('public')->exists($existingAbsensi->foto_surat)) {
                                Storage::disk('public')->delete($existingAbsensi->foto_surat);
                            }
                        }
                        $updateData['foto_surat'] = $fotoSurat;
                    }
                    $existingAbsensi->update($updateData);
                } else {
                    AbsensiJurnal::create([
                        'id_jurnal'  => $jurnal->id,
                        'id_siswa'   => $siswa->id,
                        'status'     => $status,
                        'keterangan' => $keterangan,
                        'foto_surat' => $fotoSurat,
                    ]);
                }
            }
        });

        return redirect()->route('guru.jurnal')->with('success', 'Jurnal mengajar & presensi berhasil diperbarui!');
    }

    /**
     * Stream foto kegiatan jurnal secara aman dari storage.
     */
    public function showFoto($filename)
    {
        $filename = basename($filename);

        $paths = [
            'foto_jurnal/' . $filename,
            'foto_kegiatan/' . $filename,
            $filename,
        ];

        foreach ($paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->response($path);
            }
            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->response($path);
            }
        }

        // Direct storage_path fallback
        $directPaths = [
            storage_path('app/public/foto_jurnal/' . $filename),
            storage_path('app/foto_jurnal/' . $filename),
            storage_path('app/public/foto_kegiatan/' . $filename),
            storage_path('app/foto_kegiatan/' . $filename),
        ];

        foreach ($directPaths as $dp) {
            if (file_exists($dp)) {
                return response()->file($dp);
            }
        }

        abort(404, 'Foto kegiatan tidak ditemukan.');
    }
}
