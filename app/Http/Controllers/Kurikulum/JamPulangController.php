<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPulang;
use Illuminate\Http\Request;

class JamPulangController extends Controller
{
    /**
     * Simpan/update pengaturan jam pulang per tingkat kelas per kategori hari.
     *
     * Menerima input format:
     *   jam_pulang[Senin-Kamis][X]  = 13   (atau "" untuk tidak dibatasi)
     *   jam_pulang[Senin-Kamis][XI] = 12
     *   jam_pulang[Jumat][X]        = 9
     *   dll.
     */
    public function upsert(Request $request)
    {
        $request->validate([
            'jam_pulang'                => 'required|array',
            'jam_pulang.*'              => 'array',
            'jam_pulang.*.*'            => 'nullable|integer|min:1|max:30',
            'redirect_tab'              => 'nullable|string|in:Senin-Kamis,Jumat',
        ]);

        $kategoriHariOptions = ['Senin-Kamis', 'Jumat'];
        $tingkatOptions      = ['X', 'XI', 'XII'];

        foreach ($kategoriHariOptions as $kategoriHari) {
            foreach ($tingkatOptions as $tingkat) {
                // Ambil nilai; null/kosong = tidak dibatasi
                $raw = $request->input("jam_pulang.{$kategoriHari}.{$tingkat}");
                $maxJamKe = ($raw !== null && $raw !== '') ? (int) $raw : null;

                JamPulang::updateOrCreate(
                    ['kategori_hari' => $kategoriHari, 'tingkat' => $tingkat],
                    ['max_jam_ke'    => $maxJamKe]
                );
            }
        }

        $redirectTab = $request->input('redirect_tab', 'Senin-Kamis');

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $redirectTab])
            ->with('success', 'Pengaturan jam pulang per tingkat kelas berhasil disimpan.');
    }
}
