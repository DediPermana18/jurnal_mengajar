<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\IzinGuru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IzinController extends Controller
{
    /**
     * Proteksi: hanya Guru (role 'guru') yang dapat mengajukan izin.
     */
    protected function authorizeGuru(): void
    {
        $user = Auth::user();
        abort_unless($user && in_array($user->role, ['guru', 'guru_mapel', 'wali_kelas'], true), 403, 'Akses ditolak. Halaman ini khusus untuk Guru.');
    }

    /**
     * Daftar izin milik guru yang sedang login + filter status.
     */
    public function index(Request $request)
    {
        $this->authorizeGuru();

        $filter = $request->input('status', 'Semua');

        $query = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->where('user_id', Auth::id())
            ->latest();

        if (!in_array($filter, ['Semua'], true) && in_array($filter, IzinGuru::STATUSES, true)) {
            $query->where('status', $filter);
        }

        $daftarIzin = $query->paginate(15)->withQueryString();

        $totalPending  = IzinGuru::where('user_id', Auth::id())->whereIn('status', [IzinGuru::STATUS_PENDING_PIKET, IzinGuru::STATUS_PENDING_WAKA, IzinGuru::STATUS_PENDING_KEPSEK])->count();
        $totalDisetujui = IzinGuru::where('user_id', Auth::id())->where('status', IzinGuru::STATUS_DISETUJUI)->count();
        $totalDitolak  = IzinGuru::where('user_id', Auth::id())->where('status', IzinGuru::STATUS_DITOLAK)->count();

        return view('guru.izin.index', compact('daftarIzin', 'filter', 'totalPending', 'totalDisetujui', 'totalDitolak'));
    }

    public function create()
    {
        $this->authorizeGuru();

        return view('guru.izin.form');
    }

    public function store(Request $request)
    {
        $this->authorizeGuru();

        $validated = $request->validate([
            'tanggal'      => 'required|date',
            'alasan'       => 'required|string|max:1000',
            'lampiran'     => 'nullable|string|max:7000000',
            'tugas_siswa'  => 'nullable|string|max:1000',
            'ttd_guru'     => 'nullable|string|max:150000',
        ], [
            'tanggal.required'     => 'Tanggal izin wajib diisi.',
            'tanggal.date'         => 'Format tanggal tidak valid.',
            'alasan.required'      => 'Alasan izin wajib diisi.',
            'alasan.max'           => 'Alasan maksimal :max karakter.',
            'lampiran.max'         => 'Ukuran lampiran terlalu besar.',
            'tugas_siswa.max'      => 'Tugas siswa maksimal :max karakter.',
        ]);

        $ttdGuru = isset($validated['ttd_guru']) && preg_match('/^data:image\/png;base64,/i', trim($validated['ttd_guru']))
            ? trim($validated['ttd_guru'])
            : null;

        // Lampiran / bukti surat (data URL base64) -> simpan sebagai file.
        $lampiranPath = null;
        $lampiranRaw  = (string) $request->input('lampiran');
        if (!empty($lampiranRaw) && str_contains($lampiranRaw, ',')) {
            @list(, $base64Data) = explode(',', $lampiranRaw, 2);
            if (!empty($base64Data)) {
                $decoded = base64_decode($base64Data, true);
                if ($decoded !== false && strlen($decoded) <= (5 * 1024 * 1024)) {
                    $ext      = preg_match('/^data:image\/(png|jpeg|jpg)/i', $lampiranRaw, $mImg)
                        ? (strtolower($mImg[1]) === 'png' ? 'png' : 'jpg')
                        : 'png';
                    $filename = 'izin-' . $validated['tanggal'] . '-' . strtolower(Str::random(8)) . '.' . $ext;
                    $savePath = 'lampiran_izin/' . $filename;
                    if (Storage::disk('public')->put($savePath, $decoded)) {
                        $lampiranPath = $savePath;
                    }
                }
            }
        }

        $izin = IzinGuru::create([
            'user_id'         => Auth::id(),
            'tanggal'         => $validated['tanggal'],
            'alasan'          => $validated['alasan'],
            'lampiran'        => $lampiranPath,
            'tugas_siswa'     => $validated['tugas_siswa'] ?? null,
            'status'          => IzinGuru::STATUS_PENDING_PIKET,
            'ttd_guru'        => $ttdGuru,
            'approval_token'  => (string) Str::uuid(),
        ]);

        return redirect()->route('guru.izin.index')
            ->with('success', 'Pengajuan izin berhasil dikirim. Menunggu verifikasi Guru Piket, lalu persetujuan Waka/Kepsek.');
    }

    public function show($id)
    {
        $this->authorizeGuru();

        $izin = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->where('user_id', Auth::id())
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
            && (in_array($user->role, ['admin', 'petugas_it'], true)
                || (int) $izin->user_id === (int) $user->id
                || $user->isPiketHariIni());

        abort_unless($allowed, 403, 'Akses ditolak.');

        if (!$izin->lampiran) {
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
