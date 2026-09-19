<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Guru\Concerns\ResolvesTargetGuru;
use App\Models\IzinGuru;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IzinController extends Controller
{
    use ResolvesTargetGuru;

    /**
     * Proteksi: hanya Guru (role 'guru') yang dapat mengajukan izin.
     */
    protected function authorizeGuru(): void
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->isPetugasIt() || in_array($user->effectiveRole(), ['guru', 'guru_mapel', 'wali_kelas'], true)),
            403,
            'Akses ditolak. Halaman ini khusus untuk Guru.'
        );
    }

    /**
     * Cek apakah guru memiliki pengajuan izin AKTIF pada tanggal yang dimaksud
     * (Pending Piket/Waka/Kepsek atau sudah Disetujui) yang menghalangi
     * pengajuan baru.
     *
     * Riwayat izin pada tanggal LAIN (termasuk Pending dari hari sebelumnya)
     * ATAU pengajuan di tanggal yang sama yang sudah DITOLAK tidak menghalangi.
     */
    protected function hasBlockingSubmission(int $guruId, ?string $tanggal = null): bool
    {
        $tanggalCek = $tanggal ?? today()->toDateString();

        return IzinGuru::where('user_id', $guruId)
            ->where('tanggal', $tanggalCek)
            ->whereIn('status', [
                IzinGuru::STATUS_PENDING_PIKET,
                IzinGuru::STATUS_PENDING_WAKA,
                IzinGuru::STATUS_PENDING_KEPSEK,
                IzinGuru::STATUS_DISETUJUI,
            ])
            ->exists();
    }

    /**
     * Daftar izin milik guru yang sedang login / target impersonasi + filter status.
     *
     * Saat Mode QA/IT (preview) belum memilih target guru, halaman wajib kosong
     * (0 / empty) — DILARANG fallback query ke data izin guru mana pun.
     */
    public function index(Request $request)
    {
        $this->authorizeGuru();

        $filter = $request->input('status', 'Semua');

        $query = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->latest();

        if ($this->isEmptyTargetContext()) {
            // Preview tanpa target: tidak ada fallback query, hasil wajib kosong.
            $query->whereRaw('1 = 0');
            $totalPending = 0;
            $totalDisetujui = 0;
            $totalDitolak = 0;
            $canSubmitIzin = false;
        } else {
            $guruId = $this->effectiveGuruId();
            $query->where('user_id', $guruId);

            $totalPending = IzinGuru::where('user_id', $guruId)->whereIn('status', [IzinGuru::STATUS_PENDING_PIKET, IzinGuru::STATUS_PENDING_WAKA, IzinGuru::STATUS_PENDING_KEPSEK])->count();
            $totalDisetujui = IzinGuru::where('user_id', $guruId)->where('status', IzinGuru::STATUS_DISETUJUI)->count();
            $totalDitolak = IzinGuru::where('user_id', $guruId)->where('status', IzinGuru::STATUS_DITOLAK)->count();

            $canSubmitIzin = ! $this->hasBlockingSubmission($guruId);
        }

        if (! in_array($filter, ['Semua'], true) && in_array($filter, IzinGuru::STATUSES, true)) {
            $query->where('status', $filter);
        }

        $daftarIzin = $query->paginate(15)->withQueryString();

        return view('guru.izin.index', compact('daftarIzin', 'filter', 'totalPending', 'totalDisetujui', 'totalDitolak', 'canSubmitIzin'));
    }

    public function create()
    {
        $this->authorizeGuru();

        $canSubmitIzin = $this->isEmptyTargetContext()
            ? false
            : ! $this->hasBlockingSubmission($this->effectiveGuruId());

        return view('guru.izin.form', compact('canSubmitIzin'));
    }

    public function store(Request $request)
    {
        $this->authorizeGuru();

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kategori_izin' => 'required|in:'.implode(',', array_keys(IzinGuru::KATEGORI_IZIN)),
            'keterangan' => 'nullable|string|max:1000',
            'alasan' => 'nullable|string|max:1000',
            'lampiran' => 'nullable|string|max:7000000',
            'tugas_siswa' => 'nullable|string|max:1000',
            'ttd_guru' => 'nullable|string|max:150000',
        ], [
            'tanggal.required' => 'Tanggal izin wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',
            'kategori_izin.required' => 'Pilih kategori izin terlebih dahulu.',
            'kategori_izin.in' => 'Kategori izin yang dipilih tidak valid.',
            'keterangan.max' => 'Keterangan maksimal :max karakter.',
            'alasan.required' => 'Alasan izin wajib diisi.',
            'alasan.max' => 'Alasan maksimal :max karakter.',
            'lampiran.max' => 'Ukuran lampiran terlalu besar.',
            'tugas_siswa.max' => 'Tugas siswa maksimal :max karakter.',
        ]);

        // Guard A: cegah multiple pengajuan PENDING — selama masih ada pengajuan
        // yang dalam proses verifikasi (Piket/Waka/Kepsek), guru tidak boleh
        // membuat pengajuan baru apa pun tanggalnya.
        $userId = $this->effectiveGuruId();

        $hasPending = IzinGuru::where('user_id', $userId)
            ->whereIn('status', [
                IzinGuru::STATUS_PENDING_PIKET,
                IzinGuru::STATUS_PENDING_WAKA,
                IzinGuru::STATUS_PENDING_KEPSEK,
            ])
            ->exists();

        if ($hasPending) {
            return back()->withInput()
                ->with('error', 'Anda masih memiliki pengajuan izin yang dalam proses verifikasi. Tunggu hingga disetujui/ditolak sebelum membuat pengajuan baru.');
        }

        // Guard B: cegah duplikasi/spam pengajuan izin — hanya pengajuan AKTIF
        // (Pending Piket/Waka/Kepsek atau Disetujui) pada TANGGAL YANG SAMA
        // yang menghalangi. Riwayat tanggal lain / status Ditolak dibolehkan.
        $guruId = $this->effectiveGuruId();
        if ($this->hasBlockingSubmission($guruId, $validated['tanggal'])) {
            return back()->withInput()
                ->with('error', 'Anda sudah mengajukan izin untuk hari ini atau pengajuan hari ini masih diproses.');
        }

        $ttdGuru = isset($validated['ttd_guru']) && preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_guru']))
            ? trim($validated['ttd_guru'])
            : null;

        // Alasan isi otomatis dari kategori (+ keterangan), kolom legacy untuk tampilan lama.
        $kategoriLabel = IzinGuru::kategoriLabel($validated['kategori_izin']);
        $detail = trim((string) ($validated['keterangan'] ?? ''));
        $alasan = $detail !== '' ? $kategoriLabel.' — '.$detail : $kategoriLabel;

        // Lampiran / bukti surat (data URL base64) -> simpan sebagai file.
        $lampiranPath = null;
        $lampiranRaw = (string) $request->input('lampiran');
        if (! empty($lampiranRaw) && str_contains($lampiranRaw, ',')) {
            @[, $base64Data] = explode(',', $lampiranRaw, 2);
            if (! empty($base64Data)) {
                $decoded = base64_decode($base64Data, true);
                if ($decoded !== false && strlen($decoded) <= (5 * 1024 * 1024)) {
                    $ext = preg_match('/^data:image\/(png|jpeg|jpg)/i', $lampiranRaw, $mImg)
                        ? (strtolower($mImg[1]) === 'png' ? 'png' : 'jpg')
                        : 'png';
                    $filename = 'izin-'.$validated['tanggal'].'-'.strtolower(Str::random(8)).'.'.$ext;
                    $savePath = 'lampiran_izin/'.$filename;
                    if (Storage::disk('public')->put($savePath, $decoded)) {
                        $lampiranPath = $savePath;
                    }
                }
            }
        }

        $izin = IzinGuru::create([
            'user_id' => $this->effectiveGuruId(),
            'tanggal' => $validated['tanggal'],
            'kategori_izin' => $validated['kategori_izin'],
            'keterangan' => $validated['keterangan'] ?? null,
            'alasan' => $alasan,
            'lampiran' => $lampiranPath,
            'tugas_siswa' => $validated['tugas_siswa'] ?? null,
            'status' => IzinGuru::STATUS_PENDING_PIKET,
            'ttd_guru' => $ttdGuru,
            'token_waka' => (string) Str::uuid(),
        ]);

        NotificationService::izinBaruDiajukan($izin);

        return redirect()->route('guru.izin.index')
            ->with('success', 'Pengajuan izin berhasil dikirim. Menunggu verifikasi Guru Piket, lalu persetujuan Waka/Kepsek.');
    }

    public function show($id)
    {
        $this->authorizeGuru();

        if ($this->isEmptyTargetContext()) {
            abort(404, 'Izin tidak ditemukan.');
        }

        $izin = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->where('user_id', $this->effectiveGuruId())
            ->findOrFail($id);

        return view('guru.izin.show', compact('izin'));
    }

    /**
     * Menampilkan lampiran / bukti surat izin (publik ke pemilik & approver).
     */
    public function showLampiran($id)
    {
        $izin = IzinGuru::findOrFail($id);

        $user = Auth::user();
        $allowed = $user
            && ($user->isPetugasIt()
                || $user->role === 'admin'
                || (int) $izin->user_id === (int) $user->id
                || $user->isPiketHariIni());

        abort_unless($allowed, 403, 'Akses ditolak.');

        if (! $izin->lampiran) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($izin->lampiran)) {
                return Storage::disk($disk)->response($izin->lampiran);
            }
        }

        abort(404, 'Lampiran tidak ditemukan.');
    }
}
