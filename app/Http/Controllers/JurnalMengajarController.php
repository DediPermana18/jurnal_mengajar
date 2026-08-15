<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\JadwalPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class JurnalMengajarController extends Controller
{
    /**
     * Menampilkan daftar semua jurnal mengajar
     */
    public function index()
    {
        $today = Carbon::today()->toDateString();

        $dataJurnal = Jurnal::with([
            'jadwal.guru',
            'jadwal.mapel',
            'jadwal.kelas',
            'jadwal.jamPelajaran'
        ])
        ->orderBy('tanggal', 'desc')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($jurnal) use ($today) {
            // is_editable: Admin selalu bisa edit; Guru hanya bisa edit jurnal hari ini
            $role = auth()->check() ? auth()->user()->role : null;
            $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
            $jurnal->is_editable = !$isGuru || $jurnal->tanggal === $today;
            return $jurnal;
        });

        return view('admin.jurnal.index', compact('dataJurnal', 'today'));
    }

    /**
     * Menampilkan form tambah jurnal mengajar
     */
    public function create()
    {
        $jadwals = JadwalPelajaran::with([
            'guru',
            'kelas',
            'mapel',
            'jamPelajaran',
            'tahunAjaran'
        ])->get();

        return view('admin.jurnal.create', compact('jadwals'));
    }

    /**
     * Menyimpan data jurnal mengajar baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_jadwal'        => 'required|exists:jadwal_pelajaran,id',
            'tanggal'          => 'required|date',
            'materi'           => 'required|string',
            'catatan_kejadian' => 'nullable|string',
            'foto_kegiatan'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('foto_kegiatan')) {
            $validated['foto_kegiatan'] = $request->file('foto_kegiatan')->store('foto_jurnal', 'public');
        }

        // Set otomatis waktu_isi memakai now()
        $validated['waktu_isi'] = now();

        Jurnal::create($validated);

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil ditambahkan!');
    }

    /**
     * Menampilkan form edit jurnal mengajar
     */
    public function edit($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        // DATE-LOCK: Guru Piket & Guru Mapel hanya bisa edit jurnal hari ini
        $role = auth()->check() ? auth()->user()->role : null;
        $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
        if ($isGuru && $jurnal->tanggal !== Carbon::today()->toDateString()) {
            return redirect()->back()->with('error', 'Jurnal tanggal ' . $jurnal->tanggal . ' sudah terkunci dan tidak dapat diedit.');
        }

        $jadwals = JadwalPelajaran::with([
            'guru',
            'kelas',
            'mapel',
            'jamPelajaran',
            'tahunAjaran'
        ])->get();

        return view('admin.jurnal.edit', compact('jurnal', 'jadwals'));
    }

    /**
     * Mengupdate data jurnal mengajar
     */
    public function update(Request $request, $id)
    {
        $jurnal = Jurnal::findOrFail($id);

        // DATE-LOCK: Guru Piket & Guru Mapel hanya bisa update jurnal hari ini
        $role = auth()->check() ? auth()->user()->role : null;
        $isGuru = in_array($role, ['guru_mapel', 'guru', 'wali_kelas', 'guru_piket']);
        if ($isGuru && $jurnal->tanggal !== Carbon::today()->toDateString()) {
            abort(403, 'Jurnal ini sudah terkunci. Hanya jurnal hari ini yang dapat diubah.');
        }

        $validated = $request->validate([
            'id_jadwal'        => 'required|exists:jadwal_pelajaran,id',
            'tanggal'          => 'required|date',
            'materi'           => 'required|string',
            'catatan_kejadian' => 'nullable|string',
            'foto_kegiatan'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        if ($request->hasFile('foto_kegiatan')) {
            // Hapus foto lama jika ada
            if ($jurnal->foto_kegiatan && Storage::disk('public')->exists($jurnal->foto_kegiatan)) {
                Storage::disk('public')->delete($jurnal->foto_kegiatan);
            }
            $validated['foto_kegiatan'] = $request->file('foto_kegiatan')->store('foto_jurnal', 'public');
        }

        $jurnal->update($validated);

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil diperbarui!');
    }

    /**
     * Menghapus data jurnal mengajar
     */
    public function destroy($id)
    {
        $jurnal = Jurnal::findOrFail($id);

        if ($jurnal->foto_kegiatan && Storage::disk('public')->exists($jurnal->foto_kegiatan)) {
            Storage::disk('public')->delete($jurnal->foto_kegiatan);
        }

        $jurnal->delete();

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil dihapus!');
    }
}