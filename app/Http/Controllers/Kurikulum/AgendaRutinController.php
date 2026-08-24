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
            'nama_agenda'  => 'required|string|max:100',
            'is_active'    => 'nullable|boolean',
            'redirect_tab' => 'nullable|string|in:Senin-Kamis,Jumat',
        ]);

        $isActive = $request->has('is_active') ? (bool) $request->input('is_active') : false;

        AgendaRutin::updateOrCreate(
            [
                'hari'   => $validated['hari'],
                'jam_ke' => $validated['jam_ke'],
            ],
            [
                'nama_agenda' => trim($validated['nama_agenda']),
                'is_active'   => $isActive,
            ]
        );

        $redirectTab = $request->input('redirect_tab', ($validated['hari'] === 'Jumat' ? 'Jumat' : 'Senin-Kamis'));

        $statusText = $isActive ? 'diaktifkan & dikunci' : 'dinonaktifkan';

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $redirectTab])
            ->with('success', "Agenda Rutin \"{$validated['nama_agenda']}\" ({$validated['hari']} Jam ke-{$validated['jam_ke']}) berhasil {$statusText}.");
    }
}
