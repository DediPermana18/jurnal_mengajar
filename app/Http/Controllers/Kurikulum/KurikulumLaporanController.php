<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class KurikulumLaporanController extends Controller
{
    /**
     * Query dasar jurnal mengajar yang selalu eager-load relasi yang dipakai
     * pada tabel rekap (jadwal -> kelas/mapel/jam, guru, guru pengganti).
     */
    protected function jurnalQuery(): Builder
    {
        return Jurnal::query()
            ->with([
                'guru',
                'guruPengganti',
                'jadwalPelajaran.kelas',
                'jadwalPelajaran.mapel',
                'jadwalPelajaran.jam',
                'jadwalPelajaran.guru',
            ])
            ->whereNotNull('tanggal');
    }

    /**
     * Terapkan filter tanpa paginasi:
     * - Rentang tanggal (resolve default bila kosong)
     * - Tingkat / Kelas
     * - Guru (guru asli pada jurnal ATAU guru terjadwal)
     * - Mata Pelajaran
     */
    protected function buatQuery(Request $request): array
    {
        $mulai  = trim((string) $request->input('tanggal_mulai'));
        $selesai = trim((string) $request->input('tanggal_selesai'));

        if ($mulai === '') {
            $mulai = Carbon::now()->startOfMonth()->toDateString();
        }
        if ($selesai === '') {
            $selesai = Carbon::now()->toDateString();
        }

        $query = $this->jurnalQuery()
            ->whereDate('tanggal', '>=', $mulai)
            ->whereDate('tanggal', '<=', $selesai);

        if ($tingkat = trim((string) $request->input('tingkat'))) {
            $query->whereHas('jadwalPelajaran.kelas', fn (Builder $q) => $q->where('tingkat', $tingkat));
        }

        if ($idKelas = (int) $request->input('id_kelas')) {
            $query->whereHas('jadwalPelajaran', fn (Builder $q) => $q->where('id_kelas', $idKelas));
        }

        if ($idGuru = (int) $request->input('id_guru')) {
            $query->where(function (Builder $q) use ($idGuru) {
                $q->where('id_guru', $idGuru)
                    ->orWhereHas('jadwalPelajaran', fn (Builder $j) => $j->where('id_guru', $idGuru));
            });
        }

        if ($idMapel = (int) $request->input('id_mapel')) {
            $query->whereHas('jadwalPelajaran', fn (Builder $q) => $q->where('id_mapel', $idMapel));
        }

        return [$query, $mulai, $selesai];
    }

    /**
     * Hitung metrics ringkasan untuk kartu statistik.
     */
    protected function hitungRingkasan(Builder $baseQuery, string $mulai, string $selesai): array
    {
        $totalJamKBM     = (clone $baseQuery)->count();
        $totalJurnalTerisi = (clone $baseQuery)
            ->whereNotNull('materi')
            ->where('materi', '!=', '')
            ->count();

        $guruHadir = (clone $baseQuery)->where('status_kehadiran', 'Hadir')->count();
        $guruIzin  = (clone $baseQuery)->where('status_kehadiran', 'Izin')->count();
        $guruSakit = (clone $baseQuery)->where('status_kehadiran', 'Sakit')->count();
        $guruDinas = (clone $baseQuery)->where('status_kehadiran', 'Disposisi')->count();

        return [
            'totalJamKBM'       => $totalJamKBM,
            'totalJurnalTerisi' => $totalJurnalTerisi,
            'guruHadir'         => $guruHadir,
            'guruIzin'          => $guruIzin,
            'guruSakit'         => $guruSakit,
            'guruDinas'         => $guruDinas,
            'guruTidakHadir'    => $guruIzin + $guruSakit + $guruDinas,
            'periodeMulai'      => Carbon::parse($mulai)->translatedFormat('d F Y'),
            'periodeSelesai'    => Carbon::parse($selesai)->translatedFormat('d F Y'),
        ];
    }

    protected function kelengkapanFilter(Request $request): array
    {
        $tingkat = trim((string) $request->input('tingkat'));

        return [
            'tingkatInput' => $tingkat,
            'idKelasInput' => (int) $request->input('id_kelas'),
            'idGuruInput'  => (int) $request->input('id_guru'),
            'idMapelInput' => (int) $request->input('id_mapel'),
            'tingkatList'  => Kelas::distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kelasList'    => Kelas::when($tingkat !== '', fn ($q) => $q->where('tingkat', $tingkat))
                ->orderBy('tingkat')
                ->orderBy('nama_kelas')
                ->get(),
            'guruList'  => User::where('role', 'guru')->orderBy('nama')->get(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
        ];
    }

    /**
     * Halaman utama Laporan KBM (rekap + ringkasan + filter).
     */
    public function index(Request $request)
    {
        [$query, $mulai, $selesai] = $this->buatQuery($request);

        $daftarJurnal = (clone $query)
            ->latest('tanggal')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $tahunAjaran = TahunAjaran::where('status_aktif', true)->first();

        return view('kurikulum.laporan.index', array_merge(
            $this->kelengkapanFilter($request),
            $this->hitungRingkasan($query, $mulai, $selesai),
            compact('daftarJurnal', 'tahunAjaran', 'mulai', 'selesai')
        ));
    }

    /**
     * Export Excel (.xls) — difilter sesuai query string aktif.
     */
    public function exportExcel(Request $request)
    {
        [$query, $mulai, $selesai] = $this->buatQuery($request);

        $daftarJurnal = (clone $query)
            ->latest('tanggal')
            ->latest('id')
            ->get();

        $ringkasan   = $this->hitungRingkasan($query, $mulai, $selesai);
        $tahunAjaran = TahunAjaran::where('status_aktif', true)->first();

        $html = "\xEF\xBB\xBF" . view('kurikulum.laporan.excel', array_merge(
            $ringkasan,
            compact('daftarJurnal', 'tahunAjaran', 'mulai', 'selesai')
        ))->render();

        $filename = 'laporan-kbm-' . str_replace('-', '', $mulai) . '-' . str_replace('-', '', $selesai) . '.xls';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Halaman cetak / Save PDF — memakai query string filter yang sama
     * dan tombol print browser (window.print()).
     */
    public function printPdf(Request $request)
    {
        [$query, $mulai, $selesai] = $this->buatQuery($request);

        $daftarJurnal = (clone $query)
            ->latest('tanggal')
            ->latest('id')
            ->get();

        $ringkasan   = $this->hitungRingkasan($query, $mulai, $selesai);
        $tahunAjaran = TahunAjaran::where('status_aktif', true)->first();
        $filterLabel = $this->labelFilter($request);

        return view('kurikulum.laporan.print', array_merge(
            $ringkasan,
            compact('daftarJurnal', 'tahunAjaran', 'mulai', 'selesai', 'filterLabel')
        ));
    }

    /**
     * Ringkasan filter aktif sebagai teks (dipakai pada header cetak).
     */
    protected function labelFilter(Request $request): string
    {
        $bagian = [];

        if ($tingkat = trim((string) $request->input('tingkat'))) {
            $bagian[] = 'Tingkat ' . $tingkat;
        }
        if ($idKelas = (int) $request->input('id_kelas')) {
            $kelas = Kelas::find($idKelas);
            if ($kelas) {
                $bagian[] = 'Kelas ' . $kelas->nama_kelas;
            }
        }
        if ($idGuru = (int) $request->input('id_guru')) {
            $guru = User::find($idGuru);
            if ($guru) {
                $bagian[] = 'Guru: ' . $guru->nama;
            }
        }
        if ($idMapel = (int) $request->input('id_mapel')) {
            $mapel = MataPelajaran::find($idMapel);
            if ($mapel) {
                $bagian[] = 'Mapel: ' . $mapel->nama_mapel;
            }
        }

        return implode(' · ', $bagian);
    }
}