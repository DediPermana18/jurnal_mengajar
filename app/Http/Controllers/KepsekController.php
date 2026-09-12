<?php

namespace App\Http\Controllers;

use App\Models\IzinGuru;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class KepsekController extends Controller
{
    protected function authorizeKepsek()
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        if ($user->hasPreviewRole()) {
            if ($user->previewRole() === 'kepsek') {
                return;
            }
        }

        if ($user->isPetugasIt()) {
            return;
        }

        if (! $user->isKepsek() && ! $user->isAdmin()) {
            abort(403, 'Akses khusus Kepala Sekolah.');
        }
    }

    protected function isCurrentUserKepsek(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasPreviewRole()) {
            return $user->previewRole() === 'kepsek';
        }

        return $user->isKepsek();
    }

    protected function daftarKepsek(): Collection
    {
        return User::where(function ($q) {
            $q->whereIn('role', ['kepsek', 'kepala_sekolah'])
                ->orWhere(fn ($q2) => $q2->where('role', 'admin')->whereIn('sub_role', ['kepsek', 'kepala_sekolah', 'kepala_sekolah2']));
        })->orderBy('nama')->get();
    }

    protected function resolveKepsekId(Request $request): ?int
    {
        if ($this->isCurrentUserKepsek()) {
            return Auth::id();
        }

        $kepsekId = (int) $request->input('approved_by_kepsek_id', $request->input('waka_sdm_id', 0));
        if ($kepsekId) {
            $user = User::find($kepsekId);
            if ($user) {
                return $user->id;
            }
        }

        $daftar = $this->daftarKepsek();
        if ($daftar->count() === 1) {
            return (int) $daftar->first()->id;
        }

        return $daftar->first()?->id;
    }

    public function dashboard(Request $request)
    {
        return $this->rekapIzin($request);
    }

    public function rekapIzin(Request $request)
    {
        $this->authorizeKepsek();

        $query = IzinGuru::with(['user', 'approverPiket', 'approverWaka', 'approverKepsek'])
            ->latest('tanggal');

        if ($request->has('status')) {
            $statusInput = $request->input('status');
            if ($statusInput !== '' && $statusInput !== null) {
                $query->where('status', $statusInput);
            }
        } else {
            $query->where('status', IzinGuru::STATUS_PENDING_KEPSEK);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $totalPendingKepsek = IzinGuru::where('status', IzinGuru::STATUS_PENDING_KEPSEK)->count();
        $totalDisetujui = IzinGuru::where('status', IzinGuru::STATUS_DISETUJUI)->count();
        $totalDitolak = IzinGuru::where('status', IzinGuru::STATUS_DITOLAK)->count();
        $totalPengajuan = IzinGuru::count();

        $perPage = (int) $request->input('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;
        $izinList = $query->paginate($perPage)->withQueryString();

        $daftarKepsek = $this->daftarKepsek();
        $isKepsekAuth = $this->isCurrentUserKepsek();

        return view('admin.kepsek.rekap-izin', compact(
            'izinList',
            'totalPendingKepsek',
            'totalDisetujui',
            'totalDitolak',
            'totalPengajuan',
            'daftarKepsek',
            'isKepsekAuth'
        ));
    }

    public function approveIzinSignature(Request $request, $id)
    {
        $this->authorizeKepsek();

        $izin = IzinGuru::with('user')->findOrFail($id);

        // Guard: izin data testing hanya dapat diproses oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        abort_unless(
            $izin->status === IzinGuru::STATUS_PENDING_KEPSEK,
            422,
            'Hanya izin berstatus Menunggu Persetujuan Kepala Sekolah yang dapat ditandatangani.'
        );

        $isKepsekAuth = $this->isCurrentUserKepsek();

        if ($isKepsekAuth) {
            $kepsekId = Auth::id();
        } else {
            $validated = $request->validate([
                'approved_by_kepsek_id' => 'nullable|integer|exists:users,id',
                'ttd_kepsek' => 'required|string|max:150000',
            ]);
            $kepsekId = $this->resolveKepsekId($request);
        }

        $ttdKepsek = $this->takeTtd((string) $request->input('ttd_kepsek'));
        if (! $ttdKepsek) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Tanda tangan Kepala Sekolah wajib diisi.'], 422);
            }

            return back()->with('error', 'Tanda tangan Kepala Sekolah wajib diisi.');
        }

        $izin->update([
            'approved_by_kepsek' => $kepsekId,
            'ttd_kepsek' => $ttdKepsek,
            'approved_at' => now(),
            'status' => IzinGuru::STATUS_DISETUJUI,
            'catatan_penolakan' => null,
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Izin {$izin->user->nama} berhasil DISETUJUI dan ditandatangani Kepala Sekolah.",
            ]);
        }

        return redirect()->back()
            ->with('success', "Izin {$izin->user->nama} berhasil DISETUJUI dan ditandatangani Kepala Sekolah.");
    }

    public function rejectIzin(Request $request, $id)
    {
        $this->authorizeKepsek();

        $izin = IzinGuru::with('user')->findOrFail($id);

        // Guard: izin data testing hanya dapat diproses oleh IT/QA.
        $this->authorizeTestingMutation($izin);

        abort_if(
            $izin->status === IzinGuru::STATUS_DISETUJUI || $izin->status === IzinGuru::STATUS_DITOLAK,
            422,
            'Izin ini sudah diproses.'
        );

        $validated = $request->validate([
            'catatan_penolakan' => 'required|string|min:3|max:1000',
        ]);

        $kepsekId = $this->resolveKepsekId($request);

        $izin->update([
            'status' => IzinGuru::STATUS_DITOLAK,
            'approved_at' => now(),
            'approved_by_kepsek' => $kepsekId,
            'catatan_penolakan' => $validated['catatan_penolakan'],
        ]);

        NotificationService::izinStatusChanged($izin->refresh());

        return redirect()->back()
            ->with('success', "Izin {$izin->user->nama} berhasil ditolak.");
    }

    protected function takeTtd(?string $value): ?string
    {
        return isset($value) && preg_match('/^data:image\/png;base64,/i', trim($value))
            ? trim($value)
            : null;
    }
}
