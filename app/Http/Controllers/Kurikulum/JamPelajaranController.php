<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPelajaran;
use Illuminate\Http\Request;

class JamPelajaranController extends Controller
{
    /**
     * Tampilkan daftar Master Jam Pelajaran per Tingkat & Kategori Hari.
     */
    public function index(Request $request)
    {
        $tingkatList = ['10', '11', '12'];
        $tingkat = $request->get('tingkat', '10');
        if (!in_array($tingkat, $tingkatList)) {
            $tingkat = '10';
        }

        $tab = $request->get('tab', 'Senin-Kamis');
        if (!in_array($tab, ['Senin-Kamis', 'Jumat'])) {
            $tab = 'Senin-Kamis';
        }

        // Pastikan penomoran jam_ke terurut konsisten untuk tingkat ini
        $this->syncJamKe('Senin-Kamis', $tingkat);
        $this->syncJamKe('Jumat', $tingkat);

        $seninKamis = JamPelajaran::where('kategori_hari', 'Senin-Kamis')
            ->where('tingkat', $tingkat)
            ->orderBy('jam_mulai')
            ->get();

        $jumat = JamPelajaran::where('kategori_hari', 'Jumat')
            ->where('tingkat', $tingkat)
            ->orderBy('jam_mulai')
            ->get();

        return view('kurikulum.jam_pelajaran.index', compact('seninKamis', 'jumat', 'tab', 'tingkat', 'tingkatList'));
    }

    /**
     * Simpan data jam pelajaran baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tingkat'       => 'required|in:10,11,12',
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat,upacara,pembiasaan',
        ]);

        JamPelajaran::create([
            'tingkat'       => $validated['tingkat'],
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : 1,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        $this->syncJamKe($validated['kategori_hari'], $validated['tingkat']);

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $validated['kategori_hari'], 'tingkat' => $validated['tingkat']])
            ->with('success', "Jam Pelajaran untuk Kelas {$validated['tingkat']} berhasil ditambahkan.");
    }

    /**
     * Update data jam pelajaran.
     */
    public function update(Request $request, JamPelajaran $jamPelajaran)
    {
        $validated = $request->validate([
            'tingkat'       => 'required|in:10,11,12',
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'jenis'         => 'required|in:kbm,istirahat,upacara,pembiasaan',
        ]);

        $oldHari    = $jamPelajaran->kategori_hari;
        $oldTingkat = $jamPelajaran->tingkat ?? '10';

        $jamPelajaran->update([
            'tingkat'       => $validated['tingkat'],
            'kategori_hari' => $validated['kategori_hari'],
            'jam_ke'        => $validated['jenis'] === 'istirahat' ? null : $jamPelajaran->jam_ke,
            'jam_mulai'     => $validated['jam_mulai'],
            'jam_selesai'   => $validated['jam_selesai'],
            'jenis'         => $validated['jenis'],
        ]);

        $this->syncJamKe($validated['kategori_hari'], $validated['tingkat']);
        if ($oldHari !== $validated['kategori_hari'] || $oldTingkat !== $validated['tingkat']) {
            $this->syncJamKe($oldHari, $oldTingkat);
        }

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $validated['kategori_hari'], 'tingkat' => $validated['tingkat']])
            ->with('success', "Jam Pelajaran untuk Kelas {$validated['tingkat']} berhasil diperbarui.");
    }

    /**
     * Hapus data jam pelajaran.
     */
    public function destroy(JamPelajaran $jamPelajaran)
    {
        $hari    = $jamPelajaran->kategori_hari;
        $tingkat = $jamPelajaran->tingkat ?? '10';
        $jamPelajaran->delete();

        $this->syncJamKe($hari, $tingkat);

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $hari, 'tingkat' => $tingkat])
            ->with('success', "Jam Pelajaran untuk Kelas {$tingkat} berhasil dihapus.");
    }

    /**
     * Generate preset jam pelajaran otomatis untuk tingkat tertentu.
     */
    public function generatePreset(Request $request)
    {
        $kategori = $request->input('kategori_hari', 'Senin-Kamis');
        $tingkat  = $request->input('tingkat', '10');

        // Hapus existing untuk kategori dan tingkat ini
        JamPelajaran::where('kategori_hari', $kategori)
            ->where('tingkat', $tingkat)
            ->delete();

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
                'tingkat'       => $tingkat,
                'kategori_hari' => $kategori,
                'jam_ke'        => $item['jenis'] === 'istirahat' ? null : 1,
                'jam_mulai'     => $item['jam_mulai'],
                'jam_selesai'   => $item['jam_selesai'],
                'jenis'         => $item['jenis'],
            ]);
        }

        $this->syncJamKe($kategori, $tingkat);

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $kategori, 'tingkat' => $tingkat])
            ->with('success', "Preset jam pelajaran {$kategori} untuk Kelas {$tingkat} berhasil digenerate (" . count($preset) . " slot).");
    }

    /**
     * Salin preset struktur jam pelajaran dari tingkat lain.
     */
    public function copyPreset(Request $request)
    {
        $validated = $request->validate([
            'from_tingkat'  => 'required|in:10,11,12',
            'to_tingkat'    => 'required|in:10,11,12|different:from_tingkat',
            'kategori_hari' => 'required|in:Senin-Kamis,Jumat,semua',
        ]);

        $fromTingkat  = $validated['from_tingkat'];
        $toTingkat    = $validated['to_tingkat'];
        $kategoriHari = $validated['kategori_hari'];

        $kategoriList = ($kategoriHari === 'semua') ? ['Senin-Kamis', 'Jumat'] : [$kategoriHari];
        $copiedCount = 0;

        foreach ($kategoriList as $kategori) {
            $sourceSlots = JamPelajaran::where('tingkat', $fromTingkat)
                ->where('kategori_hari', $kategori)
                ->orderBy('jam_mulai')
                ->get();

            if ($sourceSlots->isNotEmpty()) {
                // Hapus slot di tingkat target untuk kategori ini
                JamPelajaran::where('tingkat', $toTingkat)
                    ->where('kategori_hari', $kategori)
                    ->delete();

                foreach ($sourceSlots as $slot) {
                    JamPelajaran::create([
                        'tingkat'       => $toTingkat,
                        'kategori_hari' => $kategori,
                        'jam_ke'        => $slot->jam_ke,
                        'jam_mulai'     => $slot->jam_mulai,
                        'jam_selesai'   => $slot->jam_selesai,
                        'jenis'         => $slot->jenis,
                    ]);
                    $copiedCount++;
                }

                $this->syncJamKe($kategori, $toTingkat);
            }
        }

        $activeTab = ($kategoriHari === 'Jumat') ? 'Jumat' : 'Senin-Kamis';

        return redirect()
            ->route('kurikulum.jam-pelajaran.index', ['tab' => $activeTab, 'tingkat' => $toTingkat])
            ->with('success', "Berhasil menyalin {$copiedCount} slot jam pelajaran dari Kelas {$fromTingkat} ke Kelas {$toTingkat}.");
    }

    /**
     * Sinkronisasi penomoran jam_ke secara otomatis berurutan berdasarkan jam_mulai per tingkat.
     */
    private function syncJamKe(string $kategoriHari, string $tingkat): void
    {
        $items = JamPelajaran::where('kategori_hari', $kategoriHari)
            ->where('tingkat', $tingkat)
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
