<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\PengaturanJadwal;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PengaturanJadwalController extends Controller
{
    /**
     * Proteksi server-side: Admin & Kurikulum
     */
    protected function authorizeKurikulum()
    {
        $user = auth()->user();
        $role = $user ? $user->role : null;

        $isAllowed = ($role === 'admin') || in_array($role, ['admin_kurikulum', 'waka_kurikulum', 'kurikulum']);
        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengubah Pengaturan Jadwal.');
    }

    /**
     * Toggle Mode Khusus Dinamis (Senin: Upacara Ditiadakan / Jumat: Pembiasaan Ditiadakan).
     */
    public function toggleModeKhusus(Request $request)
    {
        $this->authorizeKurikulum();

        $setting = PengaturanJadwal::getSetting();
        $now     = Carbon::now();

        // Tentukan jenis mode yang dipicu
        $modeType = $request->input('mode_type');
        if (!$modeType) {
            if ($request->has('jumat_tanpa_pembiasaan') || $now->isFriday()) {
                $modeType = 'jumat';
            } else {
                $modeType = 'senin';
            }
        }

        if ($modeType === 'jumat') {
            $status = $request->has('jumat_tanpa_pembiasaan')
                ? $request->boolean('jumat_tanpa_pembiasaan')
                : $request->boolean('status');

            if ($status) {
                $jumatDate = $now->isFriday() ? $now->toDateString() : $now->next(Carbon::FRIDAY)->toDateString();
                $setting->update([
                    'jumat_tanpa_pembiasaan' => true,
                    'tanggal_eksekusi_jumat' => $jumatDate,
                ]);

                $tglFormatted = Carbon::parse($jumatDate)->translatedFormat('l, d F Y');
                return redirect()->back()->with('success', "⚡ Mode Khusus \"Jumat Tanpa Pembiasaan\" DIAKTIFKAN untuk {$tglFormatted}. Seluruh jam KBM dimajukan 1 JP.");
            } else {
                $setting->update([
                    'jumat_tanpa_pembiasaan' => false,
                    'tanggal_eksekusi_jumat' => null,
                ]);

                return redirect()->back()->with('success', 'Mode Hari Jumat dikembalikan ke NORMAL (Ada Pembiasaan).');
            }
        } else {
            // Mode Senin
            $status = $request->has('senin_tanpa_upacara')
                ? $request->boolean('senin_tanpa_upacara')
                : $request->boolean('status');

            if ($status) {
                $seninDate = $now->isMonday() ? $now->toDateString() : $now->next(Carbon::MONDAY)->toDateString();
                $setting->update([
                    'senin_tanpa_upacara' => true,
                    'tanggal_eksekusi'    => $seninDate,
                ]);

                $tglFormatted = Carbon::parse($seninDate)->translatedFormat('l, d F Y');
                return redirect()->back()->with('success', "⚡ Mode Khusus \"Senin Tanpa Upacara\" DIAKTIFKAN untuk {$tglFormatted}. Seluruh jam KBM dimajukan 1 JP.");
            } else {
                $setting->update([
                    'senin_tanpa_upacara' => false,
                    'tanggal_eksekusi'    => null,
                ]);

                return redirect()->back()->with('success', 'Mode Hari Senin dikembalikan ke NORMAL (Ada Upacara Bendera).');
            }
        }
    }

    /**
     * Alias method untuk kelangsungan route terdahulu.
     */
    public function toggleSeninTanpaUpacara(Request $request)
    {
        return $this->toggleModeKhusus($request);
    }
}
