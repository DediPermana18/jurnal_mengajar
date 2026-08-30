<?php

namespace App\Http\Controllers;

use App\Models\DispensasiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\PengaturanJadwal;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\QrCodeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
     * Akses untuk menyetujui/menolak pengajuan dispen:
     * Guru Piket hari ini, Admin, atau Petugas IT.
     */
    protected function authorizeApproval(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403, 'Silakan login terlebih dahulu.');

        $allowed = $user->isPiketHariIni()
            || in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PETUGAS_IT], true);

        abort_unless($allowed, 403, 'Akses ditolak. Hanya Guru Piket / Kesiswaan yang dapat memproses pengajuan dispen.');
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

        $totalHariIni   = DispensasiSiswa::whereDate('tanggal', $tanggal)->count();
        $totalPending   = DispensasiSiswa::where('status', DispensasiSiswa::STATUS_PENDING)->count();

        $noWaWaka = PengaturanJadwal::noWaWakaIzin();

        return view('piket.dispensasi.index', compact('dataDispensasi', 'tanggal', 'today', 'totalHariIni', 'totalPending', 'noWaWaka'));
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
     * Simpan PENGGAJUAN dispensasi baru (status pending menunggu persetujuan).
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
            'ttd_siswa'         => 'nullable|string|max:150000',
            'bukti_surat'       => 'nullable|string|max:7000000',
            'id_jadwal'         => 'nullable|exists:jadwal_pelajaran,id',
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
            'bukti_surat.max'           => 'Ukuran foto bukti terlalu besar.',
            'id_jadwal.exists'          => 'Jadwal pelajaran yang dipilih tidak valid.',
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

        // Tanda tangan digital dari canvas (data URL base64 PNG) -> disimpan apa adanya
        $ttdBase64 = isset($validated['ttd_siswa']) && preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_siswa']))
            ? trim($validated['ttd_siswa'])
            : null;

        // Foto bukti dari kamera (data URL base64 PNG/JPEG) -> decode & simpan sebagai file.
        // Ekstraksi base64 dibuat aman: jika input kosong / tidak mengandung ',' proses dilewati.
        $buktiPath = null;
        $buktiRaw  = (string) $request->input('bukti_surat');
        if (!empty($buktiRaw) && str_contains($buktiRaw, ',')) {
            @list(, $base64Data) = explode(',', $buktiRaw, 2);
            if (!empty($base64Data)) {
                $decoded = base64_decode($base64Data, true);
                if ($decoded !== false && strlen($decoded) <= (5 * 1024 * 1024)) {
                    $ext      = preg_match('/^data:image\/(png|jpeg|jpg)/i', $buktiRaw, $mImg)
                        ? (strtolower($mImg[1]) === 'png' ? 'png' : 'jpg')
                        : 'png';
                    $filename = 'dispen-' . $validated['tanggal'] . '-' . strtolower(Str::random(8)) . '.' . $ext;
                    $savePath = 'bukti_surat/' . $filename;
                    if (Storage::disk('public')->put($savePath, $decoded)) {
                        $buktiPath = $savePath;
                    }
                }
            }
        }

        $dispensasi = DispensasiSiswa::create([
            'id_siswa'       => $validated['id_siswa'],
            'id_guru_piket'  => Auth::id(),
            'id_jadwal'      => $idJadwal,
            'id_guru'        => $idGuru,
            'tanggal'        => $validated['tanggal'],
            'jam_ke'         => $jamKe,
            'alasan'         => $validated['alasan'],
            'status'         => DispensasiSiswa::STATUS_PENDING,
            'ttd_siswa'      => $ttdBase64,
            'bukti_surat'    => $buktiPath,
            'approval_token' => (string) Str::uuid(),
        ]);

        return redirect()->route('piket.dispensasi.index', ['tanggal' => $validated['tanggal']])
            ->with('success', 'Pengajuan dispensasi berhasil dikirim. Status: menunggu persetujuan Guru Piket / Kesiswaan.');
    }

    /**
     * Antrian pengajuan dispensasi untuk diproses (Setujui / Tolak).
     */
    public function pengajuan(Request $request)
    {
        $this->authorizeApproval();

        $filter = $request->get('filter', 'pending');

        $dataDispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])
            ->when($filter !== 'semua', fn ($q) => $q->where('status', $filter))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        $totalPending   = DispensasiSiswa::where('status', DispensasiSiswa::STATUS_PENDING)->count();
        $totalDisetujui = DispensasiSiswa::where('status', DispensasiSiswa::STATUS_DISETUJUI)->count();
        $totalDitolak   = DispensasiSiswa::where('status', DispensasiSiswa::STATUS_DITOLAK)->count();

        // Nomor WA Waka Kesiswaan. Prioritas: setting Pengaturan (no_wa_waka),
        // lalu fallback ke no_hp user Waka Kesiswaan di tabel users.
        // Dinormalisasi ke format internasional (62xxx) agar wa.me siap pakai.
        $noWaWaka = trim((string) (PengaturanJadwal::getSetting()->no_wa_waka ?? ''));
        if ($noWaWaka === '') {
            $noWaWaka = trim((string) (User::wakaKesiswaan()?->noHpInternasional() ?? ''));
        }
        $noWaWaka = preg_replace('/[^0-9]/', '', $noWaWaka);
        if ($noWaWaka !== '' && str_starts_with($noWaWaka, '0')) {
            $noWaWaka = '62' . substr($noWaWaka, 1);
        }

        return view('piket.dispensasi.pengajuan', compact('dataDispensasi', 'filter', 'totalPending', 'totalDisetujui', 'totalDitolak', 'noWaWaka'));
    }

    /**
     * Setujui pengajuan dispen + integrasi otomatis ke absensi jurnal.
     */
    public function approve($id)
    {
        $this->authorizeApproval();

        $dispensasi = DispensasiSiswa::with('siswa')->findOrFail($id);

        if ($dispensasi->status !== DispensasiSiswa::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan ini sudah diproses sebelumnya.');
        }

        $dispensasi->update([
            'status'             => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at'        => now(),
            'approved_by'        => Auth::id(),
            'catatan_penolakan'  => null,
        ]);

        $jumlahAbsensi = $dispensasi->terapkanKeAbsensi();

        return back()->with('success', "Pengajuan dispen '{$dispensasi->siswa->nama}' disetujui. {$jumlahAbsensi} baris absensi kelas otomatis ditandai Dispen.");
    }

    /**
     * Tolak pengajuan dispen (dengan catatan opsional).
     */
    public function tolak(Request $request, $id)
    {
        $this->authorizeApproval();

        $dispensasi = DispensasiSiswa::with('siswa')->findOrFail($id);

        if ($dispensasi->status !== DispensasiSiswa::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'catatan_penolakan' => 'nullable|string|max:500',
        ]);

        $dispensasi->update([
            'status'            => DispensasiSiswa::STATUS_DITOLAK,
            'approved_at'       => now(),
            'approved_by'       => Auth::id(),
            'catatan_penolakan' => trim($validated['catatan_penolakan'] ?? '') ?: null,
        ]);

        $dispensasi->cabutDariAbsensi();

        return back()->with('success', "Pengajuan dispen '{$dispensasi->siswa->nama}' ditolak.");
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

        $validasiUrl = route('piket.dispensasi.validasi', $dispensasi->id);
        $qrSvg       = QrCodeHelper::svg($validasiUrl, 5);

        return view('piket.dispensasi.surat', compact('dispensasi', 'qrSvg', 'validasiUrl'));
    }

    /**
     * Halaman validasi publik: dipindai lewat QR Code untuk memverifikasi keaslian surat dispen.
     */
    public function validasi($id)
    {
        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])->findOrFail($id);

        return view('piket.dispensasi.validasi', compact('dispensasi'));
    }

    /**
     * Halaman persetujuan PUBLIK (tanpa login) via link unik.
     * Menampilkan detail pengajuan + Canvas TTD Waka Kesiswaan / Penyetuju.
     * State: invalid | form | approved | rejected.
     */
    public function showApproval($token)
    {
        $dispensasi = DispensasiSiswa::with(['siswa.kelas', 'guruPiket', 'approver'])
            ->where('approval_token', $token)
            ->first();

        $state = 'invalid';
        if ($dispensasi) {
            $state = match ($dispensasi->status) {
                DispensasiSiswa::STATUS_DISETUJUI => 'approved',
                DispensasiSiswa::STATUS_DITOLAK   => 'rejected',
                default                           => 'form',
            };
        }

        return view('piket.dispensasi.approve-dispen', compact('dispensasi', 'state', 'token'));
    }

    /**
     * Proses keputusan approval publik: Setujui (wajib TTD Waka / canvas) atau Tolak.
     * Disetujui -> integrasi absensi 'Dispen' otomatis; tolak -> cabut dari absensi.
     */
    public function approvePublic(Request $request, $token)
    {
        $dispensasi = DispensasiSiswa::with('siswa')->where('approval_token', $token)->firstOrFail();

        if ($dispensasi->status !== DispensasiSiswa::STATUS_PENDING) {
            return redirect()->route('approval.show', $token)
                ->with('error', 'Pengajuan dispen ini sudah diproses sebelumnya.');
        }

        $keputusan = $request->input('keputusan');

        // === Keputusan: TOLAK ===
        if ($keputusan === 'tolak') {
            $validated = $request->validate([
                'catatan_penolakan' => 'nullable|string|max:500',
            ]);

            $dispensasi->update([
                'status'            => DispensasiSiswa::STATUS_DITOLAK,
                'approved_at'       => now(),
                'approved_by'       => null,
                'catatan_penolakan' => trim($validated['catatan_penolakan'] ?? '') ?: null,
                'ttd_waka'          => null,
            ]);

            $dispensasi->cabutDariAbsensi();

            return redirect()->route('approval.show', $token)
                ->with('success', "Pengajuan dispen '{$dispensasi->siswa->nama}' ditolak.");
        }

        // === Keputusan: SETUJUI (wajib TTD Waka) ===
        $validated = $request->validate([
            'ttd_waka' => 'nullable|string|max:150000',
        ]);

        $ttdWaka = isset($validated['ttd_waka']) && preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_waka']))
            ? trim($validated['ttd_waka'])
            : null;

        if (!$ttdWaka) {
            return redirect()->route('approval.show', $token)
                ->with('error', 'Tanda tangan Waka Kesiswaan / Penyetuju wajib diisi untuk menyetujui dispen.');
        }

        $dispensasi->update([
            'status'            => DispensasiSiswa::STATUS_DISETUJUI,
            'approved_at'       => now(),
            'approved_by'       => null,
            'catatan_penolakan' => null,
            'ttd_waka'          => $ttdWaka,
        ]);

        $dispensasi->terapkanKeAbsensi();

        return redirect()->route('approval.show', $token)
            ->with('success', "Dispen '{$dispensasi->siswa->nama}' berhasil disetujui dan ditandatangani Waka Kesiswaan / Penyetuju.");
    }
}