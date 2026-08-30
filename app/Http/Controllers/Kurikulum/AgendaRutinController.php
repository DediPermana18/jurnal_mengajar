<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\AgendaRutin;
use Illuminate\Http\Request;

class AgendaRutinController extends Controller
{
    /**
     * Simpan / perbarui konfigurasi Agenda Rutin / Upacara Sekolah.
     */
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'hari'         => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'jam_ke'       => 'required|integer|min:1|max:20',
            'is_active'    => 'nullable|boolean',
            'redirect_tab' => 'nullable|string|in:Senin-Kamis,Jumat',
        ]);

        $hari = $validated['hari'];

        // Tentukan nama agenda secara otomatis berdasarkan hari
        $namaAgenda = match ($hari) {
            'Senin' => 'Upacara Bendera',
            'Jumat' => 'Pembiasaan Jumat',
            default => 'Agenda Rutin',
        };

        $isActive = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        AgendaRutin::updateOrCreate(
            [
                'hari'   => $hari,
                'jam_ke' => $validated['jam_ke'],
            ],
            [
                'nama_agenda' => $namaAgenda,
                'is_active'   => $isActive,
            ]
        );

        $redirectTab = $request->input('redirect_tab', ($hari === 'Jumat' ? 'Jumat' : 'Senin-Kamis'));
        $statusText  = $isActive ? 'diaktifkan & dikunci' : 'dinonaktifkan';

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $redirectTab])
            ->with('success', "Agenda Rutin \"{$namaAgenda}\" (Hari {$hari} Jam ke-{$validated['jam_ke']}) berhasil {$statusText}.");
    }
}
