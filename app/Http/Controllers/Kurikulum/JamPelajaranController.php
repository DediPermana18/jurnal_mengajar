<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\AgendaRutin;
use Illuminate\Http\Request;

class JamPelajaranController extends Controller
{
    /**
     * Tampilkan daftar Master Jam Pelajaran Sekolah per Kelompok Hari.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'Senin-Kamis');
        if (!in_array($tab, ['Senin-Kamis', 'Jumat'])) {
            $tab = 'Senin-Kamis';
        }

        // Pastikan penomoran jam_ke terurut konsisten
        $this->syncJamKe('Senin-Kamis');
        $this->syncJamKe('Jumat');

        $seninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->orderBy('jam_mulai')
            ->get();

        $jumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->orderBy('jam_mulai')
            ->get();

        // Pengaturan jam pulang: lookup['kategori_hari|tingkat'] => JamPulang
        $jamPulangSettings = JamPulang::getAllAsLookup();

        // Pengaturan Agenda Rutin / Upacara Sekolah (Senin & Jumat)
        $agendaSenin = AgendaRutin::where('hari', 'Senin')->first();
        $agendaJumat = AgendaRutin::where('hari', 'Jumat')->first();
        $agendaRutin = $agendaSenin ?? AgendaRutin::first();

        // Hitung max jam_ke tersedia per kategori (untuk dropdown batas jam pulang)
        $maxJamKeSeninKamis = $seninKamis->max('jam_ke') ?? 13;
        $maxJamKeJumat      = $jumat->max('jam_ke') ?? 9;

        return view('kurikulum.jam_pelajaran.index', compact(
            'seninKamis', 'jumat', 'tab',
            'jamPulangSettings', 'maxJamKeSeninKamis', 'maxJamKeJumat',
            'agendaRutin', 'agendaSenin', 'agendaJumat'
        ));
    }

    /**
     * Simpan data jam pelajaran baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat,upacara,pembiasaan',
        ]);

        JamPelajaran::create([
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : 1,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        $this->syncJamKe($validated['kategori_hari']);

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $validated['kategori_hari']])
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil ditambahkan.");
    }

    /**
     * Update data jam pelajaran.
     */
    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $validated = $request->validate([
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat,upacara,pembiasaan',
        ]);

        $oldHari = $jamPelajaran->kategori_hari;

        $jamPelajaran->update([
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : $jamPelajaran->jam_ke,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        $this->syncJamKe($validated['kategori_hari']);
        if ($oldHari !== $validated['kategori_hari']) {
            $this->syncJamKe($oldHari);
        }

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $validated['kategori_hari']])
            ->with('success', "Jam Pelajaran ({$validated['kategori_hari']}) berhasil diperbarui.");
    }

    /**
     * Hapus data jam pelajaran.
     */
    public function destroy(JamPelajaran $jamPelajaran)
    {
        $hari = $jamPelajaran->kategori_hari;
        $jamPelajaran->delete();

        $this->syncJamKe($hari);

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $hari])
            ->with('success', "Jam Pelajaran ({$hari}) berhasil dihapus.");
    }

    /**
     * Generate preset jam pelajaran otomatis (Senin-Kamis atau Jumat).
     */
    public function generatePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        if (!in_array($kategori, ['Senin-Kamis', 'Jumat'])) {
            $kategori = 'Senin-Kamis';
        }

        // Hapus existing untuk kategori ini
        JamPelajaran::where('kategori_hari', $kategori)->delete();

        if ($kategori === 'Senin-Kamis') {
            $preset = [
                ['jam_mulai' => '07:00', 'jam_selesai' => '07:40', 'jenis' => 'kbm'],
                ['jam_mulai' => '07:40', 'jam_selesai' => '08:20', 'jenis' => 'kbm'],
                ['jam_mulai' => '08:20', 'jam_selesai' => '09:00', 'jenis' => 'kbm'],
                ['jam_mulai' => '09:00', 'jam_selesai' => '09:40', 'jenis' => 'kbm'],
                ['jam_mulai' => '09:40', 'jam_selesai' => '09:55', 'jenis' => 'istirahat'],
                ['jam_mulai' => '09:55', 'jam_selesai' => '10:35', 'jenis' => 'kbm'],
                ['jam_mulai' => '10:35', 'jam_selesai' => '11:15', 'jenis' => 'kbm'],
                ['jam_mulai' => '11:15', 'jam_selesai' => '11:55', 'jenis' => 'kbm'],
                ['jam_mulai' => '11:55', 'jam_selesai' => '12:55', 'jenis' => 'istirahat'],
                ['jam_mulai' => '12:55', 'jam_selesai' => '13:35', 'jenis' => 'kbm'],
                ['jam_mulai' => '13:35', 'jam_selesai' => '14:15', 'jenis' => 'kbm'],
                ['jam_mulai' => '14:15', 'jam_selesai' => '14:55', 'jenis' => 'kbm'],
                ['jam_mulai' => '14:55', 'jam_selesai' => '15:35', 'jenis' => 'kbm'],
            ];
        } else {
            $preset = [
                ['jam_mulai' => '07:00', 'jam_selesai' => '07:30', 'jenis' => 'pembiasaan'],
                ['jam_mulai' => '07:30', 'jam_selesai' => '08:00', 'jenis' => 'kbm'],
                ['jam_mulai' => '08:00', 'jam_selesai' => '08:30', 'jenis' => 'kbm'],
                ['jam_mulai' => '08:30', 'jam_selesai' => '09:00', 'jenis' => 'kbm'],
                ['jam_mulai' => '09:00', 'jam_selesai' => '09:30', 'jenis' => 'kbm'],
                ['jam_mulai' => '09:30', 'jam_selesai' => '09:45', 'jenis' => 'istirahat'],
                ['jam_mulai' => '09:45', 'jam_selesai' => '10:15', 'jenis' => 'kbm'],
                ['jam_mulai' => '10:15', 'jam_selesai' => '10:45', 'jenis' => 'kbm'],
                ['jam_mulai' => '10:45', 'jam_selesai' => '11:15', 'jenis' => 'kbm'],
            ];
        }

        foreach ($preset as $item) {
            JamPelajaran::create([
                'kategori_hari' => $kategori,
                'jam_ke'        => $item['jenis'] === 'istirahat' ? null : 1,
                'jam_mulai'     => $item['jam_mulai'],
                'jam_selesai'   => $item['jam_selesai'],
                'jenis'         => $item['jenis'],
            ]);
        }

        $this->syncJamKe($kategori);

        return redirect()
            ->route('admin.jam-pelajaran.index', ['tab' => $kategori])
            ->with('success', "Preset jam pelajaran {$kategori} berhasil digenerate (" . count($preset) . " slot).");
    }

    /**
     * Sinkronisasi penomoran jam_ke secara otomatis berurutan berdasarkan jam_mulai.
     */
    private function syncJamKe(string $kategoriHari): void
    {
        $items = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->orderBy('jam_mulai')
            ->get();

        $jamKeCounter = 1;
        foreach ($items as $item) {
            if ($item->jenis === 'istirahat') {
                if ($item->jam_ke !== null) {
                    $item->update(['jam_ke' => null]);
                }
            } else {
                if ($item->jam_ke !== $jamKeCounter) {
                    $item->update(['jam_ke' => $jamKeCounter]);
                }
                $jamKeCounter++;
            }
        }
    }
}
