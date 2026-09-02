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
     * Akses untuk Waka Kurikulum / Admin Kurikulum.
     */
    protected function authorizeKurikulum(): void
    {
        $user = Auth::user();
        $allowedRoles = ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu'];

        abort_unless($user instanceof User && in_array($user->role, $allowedRoles, true), 403, 'Akses ditolak. Anda tidak memiliki izin untuk approval dispensasi.');
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

        // Data jam pelajaran lengkap (dengan jam_mulai, jam_selesai) untuk auto-select berdasarkan waktu sekarang
        $jamPelajaran = JamPelajaran::whereNotNull('jam_ke')
            ->select('jam_ke', 'jam_mulai', 'jam_selesai', 'jenis')
            ->orderBy('jam_ke')
            ->get();

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

        return view('piket.dispensasi.create', compact('dataSiswa', 'jamOptions', 'jadwalOptions', 'jamPelajaran'));
    }

    /**
     * Buat dispensasi baru: Guru Piket mengisi detail & langsung menyetujui (ACC).
     * Status otomatis DISETUJUI; TTD Siswa (Pemohon) dilengkapi pada langkah berikutnya.
     */
    public function store(Request $request)
    {
        $this->authorizeGuruPiket();

        $tipe = $request->input('tipe_dispen') === DispensasiSiswa::TIPE_MASUK
            ? DispensasiSiswa::TIPE_MASUK
            : DispensasiSiswa::TIPE_KELUAR;

        if ($tipe === DispensasiSiswa::TIPE_MASUK) {
            $validated = $request->validate([
                'tanggal'        => 'required|date',
                'id_siswa'       => 'required|exists:siswa,id',
                'jam_masuk_jp'   => 'required|integer|min:1|max:20',
                'alasan_kategori' => 'required|string|max:100',
                'alasan_detail'  => 'nullable|string|max:250',
                'ttd_guru'       => 'sometimes|string|max:150000',
                'ttd_piket'      => 'sometimes|string|max:150000',
            ], [
                'tanggal.required'          => 'Tanggal dispen wajib diisi.',
                'tanggal.date'              => 'Format tanggal tidak valid.',
                'id_siswa.required'         => 'Nama siswa wajib dipilih.',
                'id_siswa.exists'           => 'Siswa yang dipilih tidak ditemukan.',
                'jam_masuk_jp.required'     => 'Pilih JP saat siswa boleh masuk kelas.',
                'jam_masuk_jp.integer'      => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.min'          => 'Nomor JP masuk tidak valid.',
                'jam_masuk_jp.max'          => 'Nomor JP masuk terlalu besar.',
                'alasan_kategori.required'  => 'Kategori alasan wajib dipilih.',
                'alasan_kategori.string'    => 'Kategori alasan tidak valid.',
                'alasan_detail.max'         => 'Detail alasan maksimal :max karakter.',
                'ttd_guru.max'              => 'Ukuran tanda tangan Guru Piket terlalu besar.',
                'ttd_piket.max'             => 'Ukuran tanda tangan Guru Piket terlalu besar.',
            ]);

            $alasan = trim((string) $validated['alasan_kategori']);
            if (!empty(trim((string) ($validated['alasan_detail'] ?? '')))) {
                $alasan .= ' — ' . trim($validated['alasan_detail']);
            }

            $jamKe      = null;
            $jamMasukJp = (int) $validated['jam_masuk_jp'];
            $jamKeluarJp = null;
            $idJadwal   = null;
            $idGuru     = null;
        } else {
            $validated = $request->validate([
                'tanggal'           => 'required|date',
                'id_siswa'          => 'required|exists:siswa,id',
                'jam_ke'            => 'required|array|min:1',
                'jam_ke.*'          => 'integer|min:1|max:20',
                'jam_keluar_jp'     => 'nullable|integer|min:1|max:20',
                'alasan'            => 'required|string|max:500',
                'id_jadwal'         => 'nullable|exists:jadwal_pelajaran,id',
                'ttd_guru'          => 'sometimes|string|max:150000',
                'ttd_piket'         => 'sometimes|string|max:150000',
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
                'jam_keluar_jp.integer'     => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.min'         => 'Nomor JP keluar tidak valid.',
                'jam_keluar_jp.max'         => 'Nomor JP keluar terlalu besar.',
                'alasan.required'           => 'Alasan kegiatan dispen wajib diisi.',
                'alasan.max'                => 'Alasan maksimal :max karakter.',
                'id_jadwal.exists'          => 'Jadwal pelajaran yang dipilih tidak valid.',
                'ttd_guru.max'              => 'Ukuran tanda tangan Guru Piket terlalu besar.',
                'ttd_piket.max'             => 'Ukuran tanda tangan Guru Piket terlalu besar.',
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

            $alasan      = $validated['alasan'];
            $jamMasukJp  = null;
            $jamKeluarJp = !empty($validated['jam_keluar_jp']) ? (int) $validated['jam_keluar_jp'] : null;
        }

        // Tanda tangan Guru Piket wajib digambar di canvas (data URL PNG) -> simpan apa adanya.
        $ttdGuruRaw = (string) ($request->input('ttd_guru') ?? $request->input('ttd_piket') ?? '');
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
            'tipe_dispen'    => $tipe,
            'jam_ke'         => $jamKe,
            'jam_keluar_jp'  => $jamKeluarJp,
            'jam_masuk_jp'   => $jamMasukJp,
            'alasan'         => $alasan,
            'status'         => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at'    => now(),
            'approved_by'    => Auth::id(),
            'ttd_guru'       => $ttdGuru,
            'approval_token' => Str::random(32),
        ]);

        // Integrasi absensi hanya untuk tipe keluar (siswa meninggalkan KBM pada jam tertentu).
        if ($tipe !== DispensasiSiswa::TIPE_MASUK) {
            $dispensasi->terapkanKeAbsensi();
        }

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

        $piket = $dispensasi->guruPiket ?? $user;

        // Tipe "Masuk Kelas" => tampilkan surat/nota masuk (tanpa TTD Waka Kesiswaan).
        if ($dispensasi->isTipeMasuk()) {
            $jamMasukDetail = $dispensasi->jam_masuk_jp
                ? JamPelajaran::where('jam_ke', $dispensasi->jam_masuk_jp)->orderBy('jam_mulai')->first()
                : null;

            return view('piket.dispensasi.surat_masuk', compact('dispensasi', 'piket', 'jamMasukDetail'));
        }

        $wakaKesiswaan = User::wakaKesiswaan();
        $jamKeluarDetail = $dispensasi->jam_keluar_jp
            ? JamPelajaran::where('jam_ke', $dispensasi->jam_keluar_jp)->orderBy('jam_mulai')->first()
            : null;

        return view('piket.dispensasi.surat', compact('dispensasi', 'piket', 'wakaKesiswaan', 'jamKeluarDetail'));
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

    /**
     * Halaman approval publik untuk Waka Kesiswaan tanpa login.
     */
    public function publicApproveView($token)
    {
        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->where('approval_token', $token)
            ->first();

        if (!$dispensasi) {
            return view('public.dispen-approval', [
                'dispensasi' => null,
                'token' => $token,
                'invalid' => true,
            ]);
        }

        if (!empty($dispensasi->ttd_waka) || $dispensasi->status === DispensasiSiswa::STATUS_APPROVED) {
            return view('public.dispen-approval', [
                'dispensasi' => $dispensasi,
                'token' => $token,
                'invalid' => false,
                'alreadySigned' => true,
            ]);
        }

        return view('public.dispen-approval', [
            'dispensasi' => $dispensasi,
            'token' => $token,
            'invalid' => false,
            'alreadySigned' => false,
        ]);
    }

    /**
     * Simpan tanda tangan Waka Kesiswaan untuk approval publik.
     */
    public function publicApproveStore(Request $request, $token)
    {
        $dispensasi = DispensasiSiswa::where('approval_token', $token)->first();

        if (!$dispensasi) {
            return redirect()->route('dispen.approval.show', $token)
                ->with('error', 'Token approval dispensasi tidak valid atau sudah kedaluwarsa.');
        }

        if (!empty($dispensasi->ttd_waka) || $dispensasi->status === DispensasiSiswa::STATUS_APPROVED) {
            return redirect()->route('dispen.approval.show', $token)
                ->with('info', 'Sudah Ditandatangani');
        }

        $validated = $request->validate([
            'ttd_waka' => 'required|string|max:150000',
        ], [
            'ttd_waka.required' => 'Tanda tangan Waka Kesiswaan wajib diisi.',
            'ttd_waka.max' => 'Ukuran tanda tangan terlalu besar.',
        ]);

        $ttdWaka = preg_match('/^data:image\/png;base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (!$ttdWaka) {
            return back()->withErrors(['ttd_waka' => 'Tanda tangan Waka Kesiswaan wajib digambar terlebih dahulu.']);
        }

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'status' => DispensasiSiswa::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => null,
        ]);

        return redirect()->route('dispen.approval.show', $token)
            ->with('success', 'Surat dispensasi berhasil ditandatangani Waka Kesiswaan.');
    }

    /**
     * Daftar dispensasi yang menunggu persetujuan Waka Kurikulum.
     */
    public function indexApproval()
    {
        $this->authorizeKurikulum();

        $daftar = DispensasiSiswa::with(['siswa.kelas', 'guruPiket'])
            ->where('status', DispensasiSiswa::STATUS_PENDING_WAKA)
            ->orderByDesc('tanggal')
            ->get();

        return view('kurikulum.dispensasi.approval', compact('daftar'));
    }

    /**
     * Waka Kurikulum menandatangani digital pengajuan dispensasi.
     */
    public function storeApproval(Request $request, $id)
    {
        $this->authorizeKurikulum();

        $dispensasi = DispensasiSiswa::findOrFail($id);

        abort_unless($dispensasi->status === DispensasiSiswa::STATUS_PENDING_WAKA, 422, 'Hanya dispensasi dengan status Pending Waka yang dapat disetujui pada tahap ini.');

        $validated = $request->validate([
            'ttd_waka' => 'required|string|max:150000',
        ], [
            'ttd_waka.required' => 'Tanda tangan Waka Kurikulum wajib diisi.',
            'ttd_waka.max' => 'Ukuran tanda tangan Waka Kurikulum terlalu besar.',
        ]);

        $ttdWaka = preg_match('/^data:image\/png;base64,/i', trim((string) $validated['ttd_waka']))
            ? trim((string) $validated['ttd_waka'])
            : null;

        if (!$ttdWaka) {
            return back()->withErrors(['ttd_waka' => 'Tanda tangan Waka Kurikulum wajib digambar terlebih dahulu.']);
        }

        $dispensasi->update([
            'ttd_waka' => $ttdWaka,
            'status' => DispensasiSiswa::STATUS_FINAL,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return redirect()->route('kurikulum.dispensasi.approval.index')
            ->with('success', 'Persetujuan dispensasi berhasil disimpan dengan tanda tangan Waka Kurikulum.');
    }
}