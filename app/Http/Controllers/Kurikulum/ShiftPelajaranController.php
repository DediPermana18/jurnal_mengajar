<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\JamPulang;
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
     * sehingga slot/kelas yang merujuk shift ini otomatis kembali "Global".
     * Pengaturan jam pulang milik shift ini ikut dihapus (shift_id = 0 = Global).
     */
    public function destroy(ShiftPelajaran $shiftPelajaran)
    {
        // Guard: data testing hanya dapat dihapus oleh IT/QA.
        $this->authorizeTestingMutation($shiftPelajaran);

        $nama = $shiftPelajaran->nama_shift;

        JamPulang::where('shift_id', $shiftPelajaran->id)->delete();
        $shiftPelajaran->delete();

        return redirect()
            ->back()
            ->with('success', "Shift '{$nama}' berhasil dihapus. Slot & kelas yang merujuknya kembali ke Global.");
    }
}