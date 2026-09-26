<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPelajaran;
use App\Models\JamPulang;
use App\Models\Kelas;
use App\Models\ShiftPelajaran;
use Illuminate\Http\Request;

/**
 * CRUD Master Shift (Shift 1 Pagi, Shift 2 Siang, dst.)
 * yang dikelola langsung dari halaman Master Jam Pelajaran.
 */
class ShiftPelajaranController extends Controller
{
    /**
     * Simpan jenis shift baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_shift' => 'required|string|max:120',
            'keterangan' => 'nullable|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'nama_shift.required' => 'Nama shift wajib diisi.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        ShiftPelajaran::create([
            'nama_shift' => trim($validated['nama_shift']),
            'keterangan' => trim($validated['keterangan'] ?? '') ?: null,
            'jam_mulai' => $validated['jam_mulai'] ?? null,
            'jam_selesai' => $validated['jam_selesai'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->back()
            ->with('success', "Shift '{$validated['nama_shift']}' berhasil ditambahkan.");
    }

    /**
     * Perbarui jenis shift.
     */
    public function update(Request $request, ShiftPelajaran $shiftPelajaran)
    {
        // Guard: data testing hanya dapat diubah oleh IT/QA.
        $this->authorizeTestingMutation($shiftPelajaran);

        $validated = $request->validate([
            'nama_shift' => 'required|string|max:120',
            'keterangan' => 'nullable|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'nama_shift.required' => 'Nama shift wajib diisi.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $shiftPelajaran->update([
            'nama_shift' => trim($validated['nama_shift']),
            'keterangan' => trim($validated['keterangan'] ?? '') ?: null,
            'jam_mulai' => $validated['jam_mulai'] ?? null,
            'jam_selesai' => $validated['jam_selesai'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->back()
            ->with('success', "Shift '{$validated['nama_shift']}' berhasil diperbarui.");
    }

    /**
     * Hapus jenis shift.
     *
     * Kolom shift_id pada jam_pelajaran & kelas memakai FK nullOnDelete,
     * sehingga slot/kelas yang merujuk shift ini otomatis "dialihkan" kembali
     * ke Global (tidak ikut terhapus). Pengaturan jam pulang milik shift ini
     * ikut dihapus (shift_id = 0 = Global).
     *
     * Dampak (jumlah slot/kelas yang dialihkan) dihitung dan disertakan pada
     * pesan sukses sebagai bentuk validasi transparan untuk admin.
     */
    public function destroy(ShiftPelajaran $shiftPelajaran)
    {
        // Guard: data testing hanya dapat dihapus oleh IT/QA.
        $this->authorizeTestingMutation($shiftPelajaran);

        $nama = $shiftPelajaran->nama_shift;

        // Hitung dampak bersih sebelum shift dihapus.
        $slotCount = JamPelajaran::where('shift_id', $shiftPelajaran->id)->count();
        $kelasCount = Kelas::where('shift_id', $shiftPelajaran->id)->count();
        $pulangCount = JamPulang::where('shift_id', $shiftPelajaran->id)->count();

        JamPulang::where('shift_id', $shiftPelajaran->id)->delete();
        $shiftPelajaran->delete();

        $pesan = "Shift '{$nama}' berhasil dihapus.";
        if ($slotCount > 0 || $kelasCount > 0) {
            $pesan .= " {$slotCount} slot jam & {$kelasCount} kelas yang terikat dialihkan ke Global (tidak turut dihapus).";
        }
        if ($pulangCount > 0) {
            $pesan .= " {$pulangCount} pengaturan jam pulang shift ikut dihapus.";
        }

        return redirect()
            ->back()
            ->with('success', $pesan);
    }
}