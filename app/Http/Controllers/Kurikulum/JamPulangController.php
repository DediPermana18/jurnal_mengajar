<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPulang;
use App\Models\ShiftPelajaran;
use Illuminate\Http\Request;

class JamPulangController extends Controller
{
    /**
     * Simpan/update pengaturan jam pulang per tingkat kelas per kategori hari,
     * terpisah per shift (multi-shift school).
     *
     * Menerima input format:
     *   jam_pulang[0][Senin-Kamis][X]  = 13   (shift 0 = Global, "" = tidak dibatasi)
     *   jam_pulang[3][Senin-Kamis][XI] = 12   (shift id 3)
     *   jam_pulang[3][Jumat][X]        = 9
     *   dll.
     */
    public function upsert(Request $request)
    {
        $request->validate([
            'jam_pulang' => 'required|array',
            'jam_pulang.*' => 'array',
            'jam_pulang.*.*' => 'array',
            'jam_pulang.*.*.*' => 'nullable|integer|min:1|max:30',
            'redirect_tab' => 'nullable|string|in:Senin-Kamis,Jumat',
            'redirect_shift' => 'nullable|integer',
            'redirect_mode' => 'nullable|string|in:global,shift',
            'redirect_ta' => 'nullable|integer',
        ]);

        $kategoriHariOptions = ['Senin-Kamis', 'Jumat'];
        $tingkatOptions = ['X', 'XI', 'XII'];

        // Auto-label data testing: Petugas IT / QA Tester (termasuk saat
        // impersonation) menyimpan is_testing_data = 1, user lain = 0.
        $isTesting = auth()->user()?->isPetugasIt() ? 1 : 0;

        $shiftKeys = array_keys((array) $request->input('jam_pulang', []));

        foreach ($shiftKeys as $shiftKey) {
            if (! is_numeric($shiftKey) || (int) $shiftKey < 0) {
                continue;
            }
            $shiftId = (int) $shiftKey;

            // Guard: shift yang tidak dikenal diabaikan (tidak boleh disisipkan).
            if ($shiftId !== 0 && ! ShiftPelajaran::whereKey($shiftId)->exists()) {
                continue;
            }

            foreach ($kategoriHariOptions as $kategoriHari) {
                foreach ($tingkatOptions as $tingkat) {
                    // Ambil nilai; null/kosong = tidak dibatasi
                    $raw = $request->input("jam_pulang.{$shiftId}.{$kategoriHari}.{$tingkat}");
                    $maxJamKe = ($raw !== null && $raw !== '') ? (int) $raw : null;

                    // Guard: simpan ulang tidak boleh menimpa data testing (kecuali IT/QA).
                    $existing = JamPulang::where('kategori_hari', $kategoriHari)
                        ->where('tingkat', $tingkat)
                        ->where('shift_id', $shiftId)
                        ->first();
                    $this->authorizeTestingMutation($existing);

                    // updateOrCreate mencegah error duplicate entry: kombinasi
                    // (kategori_hari, tingkat, shift_id, is_testing_data) kini unik.
                    JamPulang::updateOrCreate(
                        [
                            'kategori_hari' => $kategoriHari,
                            'tingkat' => $tingkat,
                            'shift_id' => $shiftId,
                            'is_testing_data' => $isTesting,
                        ],
                        ['max_jam_ke' => $maxJamKe, 'is_testing_data' => $isTesting]
                    );
                }
            }
        }

        $redirectTab = $request->input('redirect_tab', 'Senin-Kamis');
        $redirect = ['tab' => $redirectTab];

        $redirectShift = $request->input('redirect_shift');
        if ($redirectShift !== null && $redirectShift !== '' && (int) $redirectShift > 0) {
            $redirect['shift'] = (int) $redirectShift;
        }

        if ($request->input('redirect_mode') === 'shift') {
            $redirect['mode'] = 'shift';
        }

        $redirectTa = $request->input('redirect_ta');
        if ($redirectTa !== null && $redirectTa !== '' && (int) $redirectTa > 0) {
            $redirect['ta'] = (int) $redirectTa;
        }

        return redirect()
            ->route('admin.jam-pelajaran.index', $redirect)
            ->with('success', 'Pengaturan jam pulang per tingkat kelas berhasil disimpan.');
    }
}