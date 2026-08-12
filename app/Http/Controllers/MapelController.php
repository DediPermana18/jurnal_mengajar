<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use App\Models\Kelas;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\JurnalMengajar;
use Illuminate\Http\Request;

class MapelController extends Controller
{
    // 1. Menampilkan daftar semua mata pelajaran
    public function index()
    {
        $dataMapel = Mapel::with(['kelas', 'guru'])->get();
        $dataKelas = Kelas::all();
        $dataGuru  = Guru::all();

        return view('admin.mapel.index', compact('dataMapel', 'dataKelas', 'dataGuru'));
    }

    // 2. Menampilkan halaman Matriks Jadwal Harian untuk 1 kelas
    public function show($id)
    {
        // $id di sini adalah id_kelas
        $kelas        = Kelas::findOrFail($id);
        $dataKelas    = Kelas::all();

        // Ambil semua jadwal untuk kelas ini, dengan relasi guru & mapel
        $jadwals = Jadwal::with(['guru', 'mapel'])
            ->where('id_kelas', $id)
            ->orderBy('jam_mulai')
            ->get();

        // Ambil semua jurnal hari ini untuk jadwal-jadwal kelas ini
        $today        = now()->toDateString();
        $jadwalIds    = $jadwals->pluck('id_jadwal')->toArray();

        $jurnalHariIni = JurnalMengajar::with(['jadwal.guru', 'jadwal.mapel'])
            ->whereIn('id_jadwal', $jadwalIds)
            ->whereDate('tanggal', $today)
            ->get()
            ->keyBy('id_jadwal');

        return view('admin.mapel.show', compact('kelas', 'dataKelas', 'jadwals', 'jurnalHariIni', 'today'));
    }

    // 3. Menampilkan form tambah mata pelajaran
    public function create()
    {
        $dataKelas = Kelas::all();
        $dataGuru  = Guru::all();

        return view('admin.mapel.create', compact('dataKelas', 'dataGuru'));
    }

    // 3. Menyimpan data mata pelajaran baru
    public function store(Request $request)
    {
        $request->validate([
            'nama_mapel'  => 'required|string|max:100',
            'id_kelas'    => 'nullable|exists:kelas,id_kelas',
            'id_guru'     => 'nullable|exists:guru,id_guru',
            'jam_ke'      => 'nullable|string|max:50',
            'status_guru' => 'nullable|in:Masuk Kelas,Tidak Hadir,Tugas,Hadir,Izin,Sakit',
        ]);

        Mapel::create([
            'kode_mapel'  => $request->kode_mapel,
            'nama_mapel'  => $request->nama_mapel,
            'id_kelas'    => $request->id_kelas,
            'id_guru'     => $request->id_guru,
            'jam_ke'      => $request->jam_ke ?? 'Jam 1 - 4',
            'status_guru' => $request->status_guru ?? 'Masuk Kelas',
        ]);

        return redirect()->route('mapel.index')->with('success', 'Data mata pelajaran berhasil ditambahkan!');
    }

    // 4. Menampilkan form edit mata pelajaran
    public function edit($id)
    {
        $mapel     = Mapel::findOrFail($id);
        $dataKelas = Kelas::all();
        $dataGuru  = Guru::all();

        return view('admin.mapel.edit', compact('mapel', 'dataKelas', 'dataGuru'));
    }

    // 5. Mengupdate data mata pelajaran
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_mapel'  => 'required|string|max:100',
            'id_kelas'    => 'nullable|exists:kelas,id_kelas',
            'id_guru'     => 'nullable|exists:guru,id_guru',
            'jam_ke'      => 'nullable|string|max:50',
            'status_guru' => 'nullable|in:Masuk Kelas,Tidak Hadir,Tugas,Hadir,Izin,Sakit',
        ]);

        $mapel = Mapel::findOrFail($id);
        $mapel->update([
            'kode_mapel'  => $request->kode_mapel,
            'nama_mapel'  => $request->nama_mapel,
            'id_kelas'    => $request->id_kelas,
            'id_guru'     => $request->id_guru,
            'jam_ke'      => $request->jam_ke,
            'status_guru' => $request->status_guru,
        ]);

        return redirect()->route('mapel.index')->with('success', 'Data mata pelajaran berhasil diperbarui!');
    }

    // 6. Menghapus data mata pelajaran
    public function destroy($id)
    {
        $mapel = Mapel::findOrFail($id);
        $mapel->delete();

        return redirect()->route('mapel.index')->with('success', 'Data mata pelajaran berhasil dihapus!');
    }
}
