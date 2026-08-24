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
     * Sakelar / Toggle Mode Khusus Senin: Upacara Ditiadakan (Jam KBM Dimajukan).
     */
    public function toggleSeninTanpaUpacara(Request $request)
    {
        $this->authorizeKurikulum();

        $status  = $request->boolean('senin_tanpa_upacara');
        $setting = PengaturanJadwal::getSetting();

        if ($status) {
            // Tentukan tanggal hari Senin (hari ini jika Senin, atau Senin terdekat berikutnya)
            $now = Carbon::now();
            $seninDate = $now->isMonday() ? $now->toDateString() : $now->next(Carbon::MONDAY)->toDateString();

            $setting->update([
                'senin_tanpa_upacara' => true,
                'tanggal_eksekusi'    => $seninDate,
            ]);

            $tglFormatted = Carbon::parse($seninDate)->translatedFormat('l, d F Y');

            return redirect()->back()->with('success', "⚡ Mode Khusus \"Senin Tanpa Upacara\" DIAKTIFKAN untuk {$tglFormatted}. Seluruh jam KBM bergeser maju 1 JP.");
        } else {
            $setting->update([
                'senin_tanpa_upacara' => false,
                'tanggal_eksekusi'    => null,
            ]);

            return redirect()->back()->with('success', 'Mode Hari Senin dikembalikan ke NORMAL (Ada Upacara Bendera).');
        }
    }
}
