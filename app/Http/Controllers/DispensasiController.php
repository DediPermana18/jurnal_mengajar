<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DispensasiController extends Controller
{
    /**
     * Akses ditentukan oleh jadwal_piket pada hari berjalan.
     */
    protected function authorizeGuruPiket(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isPiketHariIni(), 403, 'Akses ditolak. Anda tidak mendapat jadwal piket hari ini.');
    }

    /**
     * Daftar dispensasi siswa (default: hari ini) + tombol buat dispen.
     */
    public function index(Request $request)
    {
        $this->authorizeGuruPiket();

        $today   = now()->toDateString();
        $tanggal = $request->get('tanggal', $today);

        $dataDispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->whereDate('tanggal', $tanggal)
            ->orderBy('id', 'desc')
            ->get();

        $totalHariIni = DispensasiSiswa::whereDate('tanggal', $tanggal)->count();

        return view('piket.dispensasi.index', compact('dataDispensasi', 'tanggal', 'today', 'totalHariIni'));
    }

    /**
     * Halaman form input dispensasi (siswa, jam ke-, alasan, & TTD canvas).
     * Menyertakan daftar jadwal KBM (hari/jam/mapel/guru) agar Guru Piket bisa
     * mengaitkan pengajuan ke mata pelajaran & Guru Mapel yang ditinggalkan.
     */
    public function create()
    {
        $this->authorizeGuruPiket();

        $dataSiswa = Siswa::with('kelas')
            ->where('status_siswa', 'Aktif')
            ->orderBy('nama')
            ->get();

        $jamOptions = JamPelajaran::whereNotNull('jam_ke')
            ->distinct('jam_ke')
            ->orderBy('jam_ke')
            ->pluck('jam_ke');

        $tahunAktif = TahunAjaran::where('status_aktif', true)->first();

        $jadwalQuery = JadwalPelajaran::with(['jamPelajaran', 'kelas', 'mapel', 'guru']);
        if ($tahunAktif) {
            $jadwalQuery->where('id_tahun_ajaran', $tahunAktif->id);
        }

        $jadwalOptions = $jadwalQuery->get()->map(fn (JadwalPelajaran $j) => [
            'id'       => $j->id,
            'hari'     => $j->hari,
            'jam_ke'   => (int) ($j->jamPelajaran?->jam_ke ?? 0),
            'id_kelas' => $j->id_kelas,
            'nama_kelas' => $j->kelas?->nama_kelas ?? 'Tanpa Kelas',
            'mapel'    => $j->mapel?->nama_mapel ?? '-',
            'guru'     => $j->guru?->nama ?? '-',
        ])->values();

        return view('piket.dispensasi.create', compact('dataSiswa', 'jamOptions', 'jadwalOptions'));
    }

    /**
     * Buat dispensasi baru: Guru Piket mengisi detail & langsung menyetujui (ACC).
     * Status otomatis DISETUJUI; TTD Siswa (Pemohon) dilengkapi pada langkah berikutnya.
     */
    public function store(Request $request)
    {
        $this->authorizeGuruPiket();

        $validated = $request->validate([
            'tanggal'           => 'required|date',
            'id_siswa'          => 'required|exists:siswa,id',
            'jam_ke'            => 'required|array|min:1',
            'jam_ke.*'          => 'integer|min:1|max:20',
            'alasan'            => 'required|string|max:500',
            'id_jadwal'         => 'nullable|exists:jadwal_pelajaran,id',
            'ttd_guru'          => 'required|string|max:150000',
        ], [
            'tanggal.required'          => 'Tanggal dispen wajib diisi.',
            'tanggal.date'              => 'Format tanggal tidak valid.',
            'id_siswa.required'         => 'Nama siswa wajib dipilih.',
            'id_siswa.exists'           => 'Siswa yang dipilih tidak ditemukan.',
            'jam_ke.required'           => 'Pilih minimal satu jam pelajaran.',
            'jam_ke.array'              => 'Format jam pelajaran tidak valid.',
            'jam_ke.min'                => 'Pilih minimal satu jam pelajaran.',
            'jam_ke.*.integer'          => 'Nomor jam pelajaran tidak valid.',
            'jam_ke.*.min'              => 'Nomor jam pelajaran tidak valid.',
            'jam_ke.*.max'              => 'Nomor jam pelajaran terlalu besar.',
            'alasan.required'           => 'Alasan kegiatan dispen wajib diisi.',
            'alasan.max'                => 'Alasan maksimal :max karakter.',
            'id_jadwal.exists'          => 'Jadwal pelajaran yang dipilih tidak valid.',
            'ttd_guru.required'         => 'Tanda tangan Guru Piket wajib digambar terlebih dahulu.',
            'ttd_guru.max'              => 'Ukuran tanda tangan Guru Piket terlalu besar.',
        ]);

        // Mapel / Guru Mapel yang ditinggalkan (opsional).
        // Jika id_jadwal dipilih, guru terkait diambil otomatis dari jadwal tsb.
        $idJadwal = !empty($validated['id_jadwal']) ? (int) $validated['id_jadwal'] : null;
        $idGuru   = $idJadwal
            ? (int) (JadwalPelajaran::find($idJadwal)?->id_guru ?: 0) ?: null
            : null;

        $jamKe = collect($validated['jam_ke'])
            ->map(fn ($j) => (int) $j)
            ->filter(fn ($j) => $j > 0)
            ->sort()
            ->values()
            ->implode(',');

        // Tanda tangan Guru Piket wajib digambar di canvas (data URL PNG) -> simpan apa adanya.
        $ttdGuruRaw = (string) $request->input('ttd_guru');
        $ttdGuru    = preg_match('/^data:image\/png;base64,/i', trim($ttdGuruRaw))
            ? trim($ttdGuruRaw)
            : null;

        if (!$ttdGuru) {
            return back()->withErrors(['ttd_guru' => 'Tanda tangan Guru Piket wajib digambar terlebih dahulu.']);
        }

        $dispensasi = DispensasiSiswa::create([
            'id_siswa'       => $validated['id_siswa'],
            'id_guru_piket'  => Auth::id(),
            'id_jadwal'      => $idJadwal,
            'id_guru'        => $idGuru,
            'tanggal'        => $validated['tanggal'],
            'jam_ke'         => $jamKe,
            'alasan'         => $validated['alasan'],
            'status'         => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at'    => now(),
            'approved_by'    => Auth::id(),
            'ttd_guru'       => $ttdGuru,
            'approval_token' => (string) Str::uuid(),
        ]);

        $dispensasi->terapkanKeAbsensi();

        return redirect()->route('piket.dispensasi.ttd', $dispensasi->id)
            ->with('success', 'Dispensasi telah disetujui (ACC) oleh Guru Piket. Silakan lengkapi Tanda Tangan Siswa sebagai konfirmasi akhir.');
    }

    /**
     * Halaman Surat Dispen (standalone, tanpa sidebar/navbar), siap cetak / PDF.
     * Bisa dibuka dalam status apa pun; status approval tampil jelas pada surat.
     */
    public function showSurat($id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])->findOrFail($id);

        $allowed = $user->isPiketHariIni()
            || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PETUGAS_IT], true)
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melihat surat dispen ini.');

        return view('piket.dispensasi.surat', compact('dispensasi'));
    }

    /**
     * Halaman pengisian Tanda Tangan Siswa (Pemohon) sebagai konfirmasi akhir
     * setelah dispensasi disetujui (ACC) oleh Guru Piket.
     */
    public function showTtd($id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])->findOrFail($id);

        $allowed = $user->isPiketHariIni()
            || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PETUGAS_IT], true)
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melengkapi surat dispen ini.');

        return view('piket.dispensasi.ttd', compact('dispensasi'));
    }

    /**
     * Simpan Tanda Tangan Siswa (Pemohon) pada surat dispen yang sudah disetujui.
     */
    public function saveTtd(Request $request, $id)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $dispensasi = DispensasiSiswa::with('siswa')->findOrFail($id);

        $allowed = $user->isPiketHariIni()
            || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PETUGAS_IT], true)
            || (int) $dispensasi->approved_by === (int) $user->id;

        abort_unless($allowed, 403, 'Akses ditolak. Anda tidak berwenang melengkapi surat dispen ini.');

        $validated = $request->validate([
            'ttd_siswa' => 'required|string|max:150000',
        ]);

        $ttdBase64 = preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_siswa']))
            ? trim($validated['ttd_siswa'])
            : null;

        if (!$ttdBase64) {
            return back()->with('error', 'Tanda tangan siswa wajib diisi.');
        }

        $dispensasi->update(['ttd_siswa' => $ttdBase64]);

        return redirect()->route('piket.dispensasi.surat', $dispensasi->id)
            ->with('success', 'Tanda tangan siswa berhasil disimpan. Surat dispensasi kini lengkap dan sah.');
    }
}