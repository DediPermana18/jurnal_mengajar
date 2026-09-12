<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;

class TahunAjaranController extends Controller
{
    protected function authorizePetugasTU(): void
    {
        abort_unless(
            $this->isAuthorizedAdminArea(),
            403,
            'Akses ditolak. Hanya Petugas TU yang dapat mengelola data tahun ajaran.'
        );
    }

    public function index(Request $request)
    {
        $this->authorizePetugasTU();

        $query = TahunAjaran::withCount('jadwalPelajaran');

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($tahunQuery) use ($search) {
                $tahunQuery->where('tahun_ajaran', 'like', "%{$search}%")
                    ->orWhere('semester', 'like', "%{$search}%");
            });
        }

        $tahunAjaranList = $query
            ->orderByDesc('id')
            ->get();

        return view('admin.tahun_ajaran.index', compact('tahunAjaranList'));
    }

    public function store(Request $request)
    {
        $this->authorizePetugasTU();

        $validated = $request->validate([
            'tahun_ajaran' => 'required|string|max:20|regex:/^\d{4}\/\d{4}$/',
            'semester' => 'required|in:Ganjil,Genap',
        ], [
            'tahun_ajaran.required' => 'Tahun Ajaran wajib diisi, contoh: 2025/2026.',
            'tahun_ajaran.regex' => 'Format Tahun Ajaran tidak valid, gunakan format 2025/2026.',
            'semester.required' => 'Semester wajib dipilih.',
            'semester.in' => 'Semester harus Ganjil atau Genap.',
        ]);

        $exists = TahunAjaran::where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'tahun_ajaran' => "Kombinasi Tahun Ajaran {$validated['tahun_ajaran']} Semester {$validated['semester']} sudah terdaftar.",
            ]);
        }

        TahunAjaran::create([
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'semester' => $validated['semester'],
            'is_active' => false,
        ]);

        return redirect()
            ->route('tahun-ajaran.index')
            ->with('success', "Tahun Ajaran {$validated['tahun_ajaran']} Semester {$validated['semester']} berhasil ditambahkan.");
    }

    public function update(Request $request, TahunAjaran $tahunAjaran)
    {
        $this->authorizePetugasTU();

        $validated = $request->validate([
            'tahun_ajaran' => 'required|string|max:20|regex:/^\d{4}\/\d{4}$/',
            'semester' => 'required|in:Ganjil,Genap',
        ], [
            'tahun_ajaran.required' => 'Tahun Ajaran wajib diisi, contoh: 2025/2026.',
            'tahun_ajaran.regex' => 'Format Tahun Ajaran tidak valid, gunakan format 2025/2026.',
            'semester.required' => 'Semester wajib dipilih.',
            'semester.in' => 'Semester harus Ganjil atau Genap.',
        ]);

        $exists = TahunAjaran::where('tahun_ajaran', $validated['tahun_ajaran'])
            ->where('semester', $validated['semester'])
            ->where('id', '!=', $tahunAjaran->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors([
                'tahun_ajaran' => "Kombinasi Tahun Ajaran {$validated['tahun_ajaran']} Semester {$validated['semester']} sudah terdaftar.",
            ]);
        }

        $tahunAjaran->update([
            'tahun_ajaran' => $validated['tahun_ajaran'],
            'semester' => $validated['semester'],
        ]);

        return redirect()
            ->route('tahun-ajaran.index')
            ->with('success', "Tahun Ajaran {$tahunAjaran->tahun_ajaran} Semester {$tahunAjaran->semester} berhasil diperbarui.");
    }

    public function destroy(Request $request, TahunAjaran $tahunAjaran)
    {
        $this->authorizePetugasTU();

        if ($tahunAjaran->is_active) {
            return back()->withErrors([
                'error' => "Tahun Ajaran {$tahunAjaran->tahun_ajaran} Semester {$tahunAjaran->semester} sedang Aktif dan tidak dapat dihapus.",
            ]);
        }

        $jumlahPlotted = JadwalPelajaran::where('id_tahun_ajaran', $tahunAjaran->id)->count();

        if ($jumlahPlotted > 0) {
            return back()->withErrors([
                'error' => "Tahun Ajaran {$tahunAjaran->tahun_ajaran} Semester {$tahunAjaran->semester} tidak dapat dihapus karena sudah memiliki {$jumlahPlotted} data jadwal pelajaran.",
            ]);
        }

        $tahunAjaran->delete();

        return redirect()
            ->route('tahun-ajaran.index')
            ->with('success', "Tahun Ajaran {$tahunAjaran->tahun_ajaran} Semester {$tahunAjaran->semester} berhasil dihapus.");
    }

    public function setAktif(Request $request, TahunAjaran $tahunAjaran)
    {
        $this->authorizePetugasTU();

        TahunAjaran::where('id', '!=', $tahunAjaran->id)->update(['is_active' => false]);
        $tahunAjaran->update(['is_active' => true]);

        // Reset konteks pilihan semester pada Plotting Jadwal agar seluruh sistem
        // langsung merujuk ke Tahun Ajaran / Semester aktif yang baru dipilih.
        $request->session()->forget('jadwal_selected_tahun_ajaran_id');

        return redirect()
            ->route('tahun-ajaran.index')
            ->with('success', "Tahun Ajaran {$tahunAjaran->tahun_ajaran} Semester {$tahunAjaran->semester} kini menjadi aktif.");
    }
}
