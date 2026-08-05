<?php

namespace App\Http\Controllers;

use App\Models\JurnalMengajar;
use App\Models\Jadwal;
use Illuminate\Http\Request;

class JurnalMengajarController extends Controller
{
    // 1. Menampilkan daftar semua jurnal mengajar
    public function index()
    {
        $dataJurnal = JurnalMengajar::with(['guru', 'mapel', 'kelas', 'jadwal'])
                        ->orderBy('tanggal', 'desc')
                        ->get();

        return view('jurnal.index', compact('dataJurnal'));
    }

    // 2. Menampilkan form tambah jurnal mengajar
    public function create()
    {
        $jadwals = Jadwal::with(['guru', 'kelas', 'mapel'])->get();
        return view('jurnal.create', compact('jadwals'));
    }

    // 3. Menyimpan data jurnal mengajar baru
    public function store(Request $request)
    {
        $request->validate([
            'id_jadwal'          => 'required|exists:jadwal,id_jadwal',
            'tanggal'            => 'required|date',
            'materi'             => 'required|string',
            'keterangan'         => 'nullable|string',
            'status_guru'        => 'required|in:Hadir,Izin,Sakit,Tugas',
            'jumlah_siswa_hadir' => 'required|integer|min:0',
            'semester'           => 'required|string',
            'tahun_ajaran'       => 'required|string',
        ]);

        JurnalMengajar::create([
            'id_jadwal'          => $request->id_jadwal,
            'tanggal'            => $request->tanggal,
            'materi'             => $request->materi,
            'keterangan'         => $request->keterangan,
            'status_guru'        => $request->status_guru,
            'jumlah_siswa_hadir' => $request->jumlah_siswa_hadir,
            'semester'           => $request->semester,
            'tahun_ajaran'       => $request->tahun_ajaran,
            'is_ttd'             => 0,
        ]);

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil ditambahkan!');
    }

    // 4. Menampilkan form edit jurnal mengajar
    public function edit($id)
    {
        $jurnal = JurnalMengajar::findOrFail($id);
        $jadwals = Jadwal::with(['guru', 'kelas', 'mapel'])->get();
        return view('jurnal.edit', compact('jurnal', 'jadwals'));
    }

    // 5. Mengupdate data jurnal mengajar
    public function update(Request $request, $id)
    {
        $request->validate([
            'id_jadwal'          => 'required|exists:jadwal,id_jadwal',
            'tanggal'            => 'required|date',
            'materi'             => 'required|string',
            'keterangan'         => 'nullable|string',
            'status_guru'        => 'required|in:Hadir,Izin,Sakit,Tugas',
            'jumlah_siswa_hadir' => 'required|integer|min:0',
            'semester'           => 'required|string',
            'tahun_ajaran'       => 'required|string',
        ]);

        $jurnal = JurnalMengajar::findOrFail($id);
        $jurnal->update([
            'id_jadwal'          => $request->id_jadwal,
            'tanggal'            => $request->tanggal,
            'materi'             => $request->materi,
            'keterangan'         => $request->keterangan,
            'status_guru'        => $request->status_guru,
            'jumlah_siswa_hadir' => $request->jumlah_siswa_hadir,
            'semester'           => $request->semester,
            'tahun_ajaran'       => $request->tahun_ajaran,
        ]);

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil diperbarui!');
    }

    // 6. Menghapus data jurnal mengajar
    public function destroy($id)
    {
        $jurnal = JurnalMengajar::findOrFail($id);
        $jurnal->delete();

        return redirect()->route('jurnal.index')->with('success', 'Jurnal mengajar berhasil dihapus!');
    }
}