<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\PengaturanJadwal;
use Illuminate\Http\Request;

class IzinSettingController extends Controller
{
    /**
     * Proteksi server-side: Admin & Kurikulum.
     */
    protected function authorizeKurikulum(): void
    {
        $role = auth()->user()?->role;
        $isAllowed = in_array($role, ['admin', 'admin_kurikulum', 'waka_kurikulum', 'kurikulum', 'admin_tu'], true);

        abort_unless($isAllowed, 403, 'Akses ditolak. Anda tidak memiliki izin untuk mengubah Pengaturan Approval Izin.');
    }

    /**
     * Halaman pengaturan alur approval izin guru (level & nomor WA Waka/Kepsek).
     */
    public function index()
    {
        $this->authorizeKurikulum();

        $setting  = PengaturanJadwal::getSetting();
        $level    = PengaturanJadwal::izinApprovalLevel();
        $noWaWaka = PengaturanJadwal::noWaWakaIzin();
        $noWaKepsek = PengaturanJadwal::noWaKepsek();

        return view('kurikulum.izin.setting', compact('setting', 'level', 'noWaWaka', 'noWaKepsek'));
    }

    /**
     * Simpan pengaturan alur approval izin.
     */
    public function update(Request $request)
    {
        $this->authorizeKurikulum();

        $validated = $request->validate([
            'izin_approval_level' => 'required|integer|in:1,2,3',
            'no_wa_waka'          => 'nullable|string|max:20',
            'no_wa_kepsek'        => 'nullable|string|max:20',
        ], [
            'izin_approval_level.required' => 'Level approval wajib dipilih.',
            'izin_approval_level.in'       => 'Level approval tidak valid.',
            'no_wa_waka.max'               => 'Nomor WA Waka maksimal :max karakter.',
            'no_wa_kepsek.max'             => 'Nomor WA Kepsek maksimal :max karakter.',
        ]);

        $setting = PengaturanJadwal::getSetting();

        $setting->update([
            'izin_approval_level' => (int) $validated['izin_approval_level'],
            'no_wa_waka'          => $this->normalize($validated['no_wa_waka'] ?? ''),
            'no_wa_kepsek'        => $this->normalize($validated['no_wa_kepsek'] ?? ''),
        ]);

        return redirect()->route('kurikulum.izin.setting')
            ->with('success', 'Pengaturan alur approval Izin Guru berhasil disimpan.');
    }

    protected function normalize(?string $value): ?string
    {
        $no = preg_replace('/[^0-9]/', '', trim((string) $value));
        if ($no === '') {
            return null;
        }
        if (str_starts_with($no, '0')) {
            $no = '62' . substr($no, 1);
        }
        return $no;
    }
}
