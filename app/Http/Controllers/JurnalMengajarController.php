<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\JadwalPelajaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class JurnalMengajarController extends Controller
{
    /**
     * Menampilkan daftar semua jurnal mengajar
     */
    public function index()
    {
        $today = Carbon::today()->toDateString();

        $dataJurnal = Jurnal::with([
            'guru',
            'guruPengganti',
            'jadwal.guru',
            'jadwal.mapel',
            'jadwal.kelas',
            'jadwal.jamPelajaran'
        ])
        ->orderBy('tanggal', 'desc')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($jurnal) use ($today) {
            // is_editable: Admin selalu bisa edit; Guru hanya bisa edit jurnal hari ini
            $role = auth()->check() ? auth()->user()->role : null;
            $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
            $jurnal->is_editable = !$isGuru || $jurnal->tanggal === $today;
            return $jurnal;
        });

        return view('admin.jurnal.index', compact('dataJurnal', 'today'));
    }

    /**
     * Menampilkan form tambah jurnal mengajar
     */
    public function create()
    {
        $jadwals = JadwalPelajaran::with([
            'guru',
            'kelas',
            'mapel',
            'jamPelajaran',
            'tahunAjaran'
        ])->get();

        $gurus = User::orderBy('nama')->get();

        return view('admin.jurnal.create', compact('jadwals', 'gurus'));
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
     * Menyimpan data jurnal mengajar baru (dengan pengisian otomatis Multi-Jam berbasis group_id)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_jadwal'         => 'required|exists:jadwal_pelajaran,id',
            'tanggal'           => 'required|date',
            'materi'            => 'required|string',
            'catatan_kejadian'  => 'nullable|string',
            'foto_kegiatan'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status_kehadiran'   => 'nullable|in:Hadir,Izin,Sakit,Disposisi',
            'id_guru_pengganti' => 'nullable|exists:users,id',
        ]);

        $targetJadwal = JadwalPelajaran::with(['kelas', 'guru', 'jamPelajaran'])->findOrFail($validated['id_jadwal']);

        $fotoKegiatanPath = null;
        if ($request->hasFile('foto_kegiatan')) {
            $namaKelas = $this->sanitizeString($targetJadwal->kelas?->nama_kelas);
            $guruIdNip = $targetJadwal->guru?->nip ?? $targetJadwal->id_guru ?? auth()->id();
            $tglStr    = Carbon::parse($validated['tanggal'])->format('Ymd');
            $jamKe     = $targetJadwal->jamPelajaran?->jam_ke ?? 1;
            $hash      = substr(md5(uniqid((string) time(), true)), 0, 6);
            $ext       = $request->file('foto_kegiatan')->getClientOriginalExtension() ?: 'jpg';

            $filename  = "JRN_{$namaKelas}_{$guruIdNip}_{$tglStr}_{$jamKe}_{$hash}.{$ext}";
            $fotoKegiatanPath = $request->file('foto_kegiatan')->storeAs('foto_jurnal', $filename, 'local');
        }

        // Cari semua jadwal pelajaran dalam satu kelompok/sesi (group_id)
        if ($targetJadwal->group_id) {
            $groupSchedules = JadwalPelajaran::where('group_id', $targetJadwal->group_id)->get();
        } else {
            $groupSchedules = collect([$targetJadwal]);
        }

        $statusKehadiran = $validated['status_kehadiran'] ?? 'Hadir';
        $idGuruPengganti = $validated['id_guru_pengganti'] ?? null;

        // Loop insert record jurnal untuk SEMUA id_jadwal dalam kelompok tersebut
        foreach ($groupSchedules as $sched) {
            Jurnal::firstOrCreate(
                [
                    'id_jadwal' => $sched->id,
                    'tanggal'   => $validated['tanggal'],
                ],
                [
                    'id_guru'           => $sched->id_guru,
                    'id_guru_pengganti' => $idGuruPengganti,
                    'status_kehadiran'  => $statusKehadiran,
                    'materi'            => $validated['materi'],
                    'catatan_kejadian'  => $validated['catatan_kejadian'] ?? null,
                    'foto_kegiatan'     => $fotoKegiatanPath,
                    'waktu_isi'         => now(),
                ]
            );
        }

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil ditambahkan untuk semua jam dalam sesi tersebut!');
    }

    /**
     * Menampilkan form edit jurnal mengajar
     */
    public function edit($id)
    {
        $jurnal = Jurnal::with(['guru', 'guruPengganti', 'jadwal'])->findOrFail($id);

        // DATE-LOCK: Guru Piket & Guru Mapel hanya bisa edit jurnal hari ini
        $role = auth()->check() ? auth()->user()->role : null;
        $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
        if ($isGuru && $jurnal->tanggal !== Carbon::today()->toDateString()) {
            return redirect()->back()->with('error', 'Jurnal tanggal ' . $jurnal->tanggal . ' sudah terkunci dan tidak dapat diedit.');
        }

        $jadwals = JadwalPelajaran::with([
            'guru',
            'kelas',
            'mapel',
            'jamPelajaran',
            'tahunAjaran'
        ])->get();

        $gurus = User::orderBy('nama')->get();

        return view('admin.jurnal.edit', compact('jurnal', 'jadwals', 'gurus'));
    }

    /**
     * Mengupdate data jurnal mengajar
     */
    public function update(Request $request, $id)
    {
        $jurnal = Jurnal::findOrFail($id);

        // DATE-LOCK: Guru Piket & Guru Mapel hanya bisa update jurnal hari ini
        $role = auth()->check() ? auth()->user()->role : null;
        $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
        if ($isGuru && $jurnal->tanggal !== Carbon::today()->toDateString()) {
            abort(403, 'Jurnal ini sudah terkunci. Hanya jurnal hari ini yang dapat diubah.');
        }

        $validated = $request->validate([
            'id_jadwal'         => 'required|exists:jadwal_pelajaran,id',
            'tanggal'           => 'required|date',
            'materi'            => 'required|string',
            'catatan_kejadian'  => 'nullable|string',
            'foto_kegiatan'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status_kehadiran'   => 'required|in:Hadir,Izin,Sakit,Disposisi',
            'id_guru_pengganti' => 'nullable|exists:users,id',
        ]);

        if ($request->hasFile('foto_kegiatan')) {
            // Hapus foto lama dari private / public disk jika ada
            if ($jurnal->foto_kegiatan) {
                if (Storage::disk('local')->exists($jurnal->foto_kegiatan)) {
                    Storage::disk('local')->delete($jurnal->foto_kegiatan);
                } elseif (Storage::disk('public')->exists($jurnal->foto_kegiatan)) {
                    Storage::disk('public')->delete($jurnal->foto_kegiatan);
                }
            }

            $targetJadwal = JadwalPelajaran::with(['kelas', 'guru', 'jamPelajaran'])->find($validated['id_jadwal']) ?? $jurnal->jadwal;
            $namaKelas    = $this->sanitizeString($targetJadwal?->kelas?->nama_kelas);
            $guruIdNip    = $targetJadwal?->guru?->nip ?? $targetJadwal?->id_guru ?? auth()->id();
            $tglStr       = Carbon::parse($validated['tanggal'])->format('Ymd');
            $jamKe        = $targetJadwal?->jamPelajaran?->jam_ke ?? 1;
            $hash         = substr(md5(uniqid((string) time(), true)), 0, 6);
            $ext          = $request->file('foto_kegiatan')->getClientOriginalExtension() ?: 'jpg';

            $filename     = "JRN_{$namaKelas}_{$guruIdNip}_{$tglStr}_{$jamKe}_{$hash}.{$ext}";
            $validated['foto_kegiatan'] = $request->file('foto_kegiatan')->storeAs('foto_jurnal', $filename, 'local');
        }

        // Pastikan id_guru terisi dari jadwal jika kosong
        if (empty($jurnal->id_guru)) {
            $jadwal = JadwalPelajaran::find($validated['id_jadwal']);
            $validated['id_guru'] = $jadwal?->id_guru;
        }

        $jurnal->update($validated);

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil diperbarui!');
    }

    /**
     * Method Khusus Guru Piket / Admin TU untuk update Status Kehadiran Guru & Guru Pengganti (Split Piket)
     */
    public function updateByPiket(Request $request, $id)
    {
        $user = auth()->user();
        $role = $user ? $user->role : null;

        // Otorisasi role: Guru Piket, Admin TU, Admin
        if (!in_array($role, ['guru_piket', 'admin_tu', 'admin', 'superadmin'])) {
            abort(403, 'Akses ditolak. Fitur ini khusus untuk Guru Piket / Admin TU.');
        }

        $jurnal = Jurnal::findOrFail($id);

        $validated = $request->validate([
            'status_kehadiran'   => 'required|in:Hadir,Izin,Sakit,Disposisi',
            'id_guru_pengganti' => 'nullable|exists:users,id',
            'catatan_kejadian'  => 'nullable|string',
            'materi'            => 'nullable|string',
        ]);

        // Jika status bukan Hadir dan guru pengganti belum dipilih, otomatis isi dengan ID Guru Piket yang login
        if ($validated['status_kehadiran'] !== 'Hadir' && empty($validated['id_guru_pengganti'])) {
            $validated['id_guru_pengganti'] = $user ? $user->id : null;
        }

        // Pastikan id_guru terisi dari jadwal jika belum ada
        if (empty($jurnal->id_guru) && $jurnal->jadwal) {
            $validated['id_guru'] = $jurnal->jadwal->id_guru;
        }

        $jurnal->update(array_filter($validated, fn ($val) => $val !== null));

        return redirect()->back()->with('success', 'Status Kehadiran & Guru Pengganti berhasil diperbarui oleh Piket/TU!');
    }

    /**
     * Stream foto kegiatan jurnal secara aman dari private storage.
     */
    public function showFoto($filename)
    {
        $filename = basename($filename);

        $paths = [
            'foto_jurnal/' . $filename,
            'foto_surat/' . $filename,
            'foto_kegiatan/' . $filename,
            $filename,
        ];

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->response($path);
            }
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->response($path);
            }
        }

        // Direct storage_path fallback (private storage)
        $directPaths = [
            storage_path('app/private/foto_jurnal/' . $filename),
            storage_path('app/private/foto_surat/' . $filename),
            storage_path('app/private/foto_kegiatan/' . $filename),
            storage_path('app/public/foto_jurnal/' . $filename),
            storage_path('app/public/foto_surat/' . $filename),
            storage_path('app/public/foto_kegiatan/' . $filename),
        ];

        foreach ($directPaths as $dp) {
            if (file_exists($dp)) {
                return response()->file($dp);
            }
        }

        abort(404, 'Foto kegiatan tidak ditemukan.');
    }

    /**
     * Menghapus data jurnal mengajar
     */
    public function destroy($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        if ($jurnal->foto_kegiatan) {
            if (Storage::disk('local')->exists($jurnal->foto_kegiatan)) {
                Storage::disk('local')->delete($jurnal->foto_kegiatan);
            } elseif (Storage::disk('public')->exists($jurnal->foto_kegiatan)) {
                Storage::disk('public')->delete($jurnal->foto_kegiatan);
            }
        }

        $jurnal->delete();

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil dihapus!');
    }
}