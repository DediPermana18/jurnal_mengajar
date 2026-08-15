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
     * Evaluasi hak akses & status locking per jadwal.
     */
    protected function evaluateJadwal(JadwalPelajaran $jadwal, ?Jurnal $jurnalHariIni = null): array
    {
        $user = auth()->user();
        $now = Carbon::now();
        $today = Carbon::today()->toDateString();

        $isOwner = (int) $jadwal->id_guru === (int) $user->id;
        $jam = $jadwal->jamPelajaran;

        $jamMulai = $jam
            ? Carbon::today()->setTimeFromTimeString($jam->jam_mulai)
            : null;

        $isTimeReached = $jamMulai ? $now->gte($jamMulai) : false;

        $jurnal = $jurnalHariIni ?? Jurnal::where('id_jadwal', $jadwal->id)
            ->whereDate('tanggal', $today)
            ->first();

        $isFilled = $jurnal !== null;

        $canFill = $isOwner && $isTimeReached && !$isFilled;

        $lockReason = null;
        if (!$isOwner) {
            $lockReason = 'Bukan jadwal mengajar Anda';
        } elseif (!$isTimeReached) {
            $lockReason = 'Belum waktunya jam pelajaran';
        } elseif ($isFilled) {
            $lockReason = 'Jurnal sudah terisi';
        }

        return [
            'is_owner'        => $isOwner,
            'is_time_reached' => $isTimeReached,
            'is_filled'       => $isFilled,
            'can_fill'        => $canFill,
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
                    'can_fill'    => $eval['can_fill'],
                    'lock_reason' => $eval['lock_reason'],
                ];
            });

        return view('guru.jurnal.index', compact('jadwals', 'hari', 'today'));
    }

    /**
     * Form pengisian jurnal & presensi.
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
     * Simpan jurnal & absensi siswa.
     */
    public function store(Request $request)
    {
        $this->authorizeGuru();

        $validated = $request->validate([
            'id_jadwal'                    => 'required|exists:jadwal_pelajaran,id',
            'materi'                       => 'required|string',
            'tidak_hadir'                  => 'nullable|array',
            'tidak_hadir.*'                => 'exists:siswa,id',
            'status'                       => 'nullable|array',
            'status.*'                     => 'in:Sakit,Izin,Alpa,Dispen',
            'keterangan'                   => 'nullable|array',
            'keterangan.*'                 => 'nullable|string|max:500',
            'foto_surat'                   => 'nullable|array',
            'foto_surat.*'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $jadwal = JadwalPelajaran::with(['kelas', 'mapel', 'jamPelajaran'])->findOrFail($validated['id_jadwal']);
        $eval = $this->evaluateJadwal($jadwal);

        if (!$eval['can_fill']) {
            return redirect()
                ->route('guru.jurnal')
                ->with('error', $eval['lock_reason'] ?? 'Jadwal ini tidak dapat diisi saat ini.');
        }

        $tidakHadirIds = collect($validated['tidak_hadir'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $statusMap = $validated['status'] ?? [];
        $keteranganMap = $validated['keterangan'] ?? [];

        foreach ($tidakHadirIds as $siswaId) {
            if (empty($statusMap[$siswaId])) {
                return back()
                    ->withInput()
                    ->withErrors(["status.{$siswaId}" => 'Pilih jenis ketidakhadiran untuk siswa yang tidak hadir.']);
            }
        }

        $siswas = Siswa::where('id_kelas', $jadwal->id_kelas)
            ->where('status_siswa', 'Aktif')
            ->get();

        $invalidSiswa = $tidakHadirIds->diff($siswas->pluck('id'));
        if ($invalidSiswa->isNotEmpty()) {
            return back()->withInput()->with('error', 'Data siswa tidak valid untuk kelas ini.');
        }

        DB::transaction(function () use ($validated, $jadwal, $tidakHadirIds, $statusMap, $keteranganMap, $request, $siswas) {
            $jurnal = Jurnal::create([
                'id_jadwal'  => $jadwal->id,
                'tanggal'    => Carbon::today()->toDateString(),
                'materi'     => $validated['materi'],
                'waktu_isi'  => now(),
            ]);

            foreach ($siswas as $siswa) {
                $isTidakHadir = $tidakHadirIds->contains($siswa->id);
                $status = $isTidakHadir ? ($statusMap[$siswa->id] ?? 'Alpa') : 'Hadir';
                $keterangan = $isTidakHadir ? ($keteranganMap[$siswa->id] ?? null) : null;
                $fotoSurat = null;

                if ($isTidakHadir && $request->hasFile("foto_surat.{$siswa->id}")) {
                    $fotoSurat = $request->file("foto_surat.{$siswa->id}")->store('foto_surat_absensi', 'public');
                }

                AbsensiJurnal::create([
                    'id_jurnal'   => $jurnal->id,
                    'id_siswa'    => $siswa->id,
                    'status'      => $status,
                    'keterangan'  => $keterangan,
                    'foto_surat'  => $fotoSurat,
                ]);
            }
        });

        return redirect()
            ->route('guru.jurnal')
            ->with('success', 'Jurnal mengajar dan presensi siswa berhasil disimpan!');
    }
}
