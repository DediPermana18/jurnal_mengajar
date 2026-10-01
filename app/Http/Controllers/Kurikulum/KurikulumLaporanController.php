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
        $mulai = trim((string) $request->input('tanggal_mulai'));
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
        $totalJamKBM = (clone $baseQuery)->count();
        $totalJurnalTerisi = (clone $baseQuery)
            ->whereNotNull('materi')
            ->where('materi', '!=', '')
            ->count();

        $guruHadir = (clone $baseQuery)->where('status_kehadiran', 'Hadir')->count();
        $guruIzin = (clone $baseQuery)->where('status_kehadiran', 'Izin')->count();
        $guruSakit = (clone $baseQuery)->where('status_kehadiran', 'Sakit')->count();
        $guruDinas = (clone $baseQuery)->where('status_kehadiran', 'Disposisi')->count();

        return [
            'totalJamKBM' => $totalJamKBM,
            'totalJurnalTerisi' => $totalJurnalTerisi,
            'guruHadir' => $guruHadir,
            'guruIzin' => $guruIzin,
            'guruSakit' => $guruSakit,
            'guruDinas' => $guruDinas,
            'guruTidakHadir' => $guruIzin + $guruSakit + $guruDinas,
            'periodeMulai' => Carbon::parse($mulai)->translatedFormat('d F Y'),
            'periodeSelesai' => Carbon::parse($selesai)->translatedFormat('d F Y'),
        ];
    }

    protected function kelengkapanFilter(Request $request): array
    {
        $tingkat = trim((string) $request->input('tingkat'));

        return [
            'tingkatInput' => $tingkat,
            'idKelasInput' => (int) $request->input('id_kelas'),
            'idGuruInput' => (int) $request->input('id_guru'),
            'idMapelInput' => (int) $request->input('id_mapel'),
            'tingkatList' => Kelas::distinct()->orderBy('tingkat')->pluck('tingkat'),
            'kelasList' => Kelas::with('jurusan')
                ->when($tingkat !== '', fn ($q) => $q->where('tingkat', $tingkat))
                ->orderBy('tingkat')
                ->orderBy('nama_kelas')
                ->get(),
            'guruList' => User::where('role', 'guru')->orderBy('nama')->get(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
        ];
    }

    /**
     * Grouping sesi KBM berturut-turut yang memiliki data identik:
     * tanggal, kelas, guru, guru_pengganti, mapel, materi, catatan_kejadian, status_kehadiran.
     */
    public function groupJurnalSessions($jurnalCollection)
    {
        if ($jurnalCollection->isEmpty()) {
            return collect();
        }

        // Urutkan data secara teratur berdasarkan tanggal (desc), kelas, guru, mapel, dan jam_ke (asc)
        $sorted = $jurnalCollection->sortBy([
            ['tanggal', 'desc'],
            function ($a, $b) {
                $kelasA = $a->jadwalPelajaran?->id_kelas ?? 0;
                $kelasB = $b->jadwalPelajaran?->id_kelas ?? 0;
                if ($kelasA !== $kelasB) {
                    return $kelasA <=> $kelasB;
                }

                $guruA = $a->id_guru ?? $a->jadwalPelajaran?->id_guru ?? 0;
                $guruB = $b->id_guru ?? $b->jadwalPelajaran?->id_guru ?? 0;
                if ($guruA !== $guruB) {
                    return $guruA <=> $guruB;
                }

                $mapelA = $a->jadwalPelajaran?->id_mapel ?? 0;
                $mapelB = $b->jadwalPelajaran?->id_mapel ?? 0;
                if ($mapelA !== $mapelB) {
                    return $mapelA <=> $mapelB;
                }

                $jamA = $a->jadwalPelajaran?->jam?->jam_ke ?? 0;
                $jamB = $b->jadwalPelajaran?->jam?->jam_ke ?? 0;

                return $jamA <=> $jamB;
            },
        ]);

        $grouped = collect();
        $currentGroup = null;

        foreach ($sorted as $jurnal) {
            $jadwal = $jurnal->jadwalPelajaran;
            $jam = $jadwal?->jam;

            $tanggal = $jurnal->tanggal ? $jurnal->tanggal->format('Y-m-d') : '';
            $kelasId = $jadwal?->id_kelas ?? 0;
            $guruId = $jurnal->id_guru ?? $jadwal?->id_guru ?? 0;
            $guruPenggantiId = $jurnal->id_guru_pengganti ?? 0;
            $mapelId = $jadwal?->id_mapel ?? 0;
            $materi = trim((string) $jurnal->materi);
            $catatan = trim((string) $jurnal->catatan_kejadian);
            $status = trim((string) $jurnal->status_kehadiran);
            $jamKe = $jam?->jam_ke !== null ? (int) $jam->jam_ke : null;

            if ($currentGroup === null) {
                $currentGroup = [
                    'jurnal' => $jurnal,
                    'items' => collect([$jurnal]),
                    'tanggal' => $tanggal,
                    'kelas_id' => $kelasId,
                    'guru_id' => $guruId,
                    'guru_pengganti_id' => $guruPenggantiId,
                    'mapel_id' => $mapelId,
                    'materi' => $materi,
                    'catatan' => $catatan,
                    'status' => $status,
                    'last_jam_ke' => $jamKe,
                    'jam_kes' => $jamKe !== null ? [$jamKe] : [],
                ];
                continue;
            }

            $isSameGroup = ($currentGroup['tanggal'] === $tanggal)
                && ($currentGroup['kelas_id'] === $kelasId)
                && ($currentGroup['guru_id'] === $guruId)
                && ($currentGroup['guru_pengganti_id'] === $guruPenggantiId)
                && ($currentGroup['mapel_id'] === $mapelId)
                && ($currentGroup['materi'] === $materi)
                && ($currentGroup['catatan'] === $catatan)
                && ($currentGroup['status'] === $status);

            $isConsecutive = false;
            if ($isSameGroup) {
                if ($currentGroup['last_jam_ke'] !== null && $jamKe !== null) {
                    if ($jamKe === $currentGroup['last_jam_ke'] + 1 || $jamKe === $currentGroup['last_jam_ke']) {
                        $isConsecutive = true;
                    }
                } elseif ($currentGroup['last_jam_ke'] === null && $jamKe === null) {
                    $isConsecutive = true;
                }
            }

            if ($isSameGroup && $isConsecutive) {
                $currentGroup['items']->push($jurnal);
                $currentGroup['last_jam_ke'] = $jamKe;
                if ($jamKe !== null && ! in_array($jamKe, $currentGroup['jam_kes'], true)) {
                    $currentGroup['jam_kes'][] = $jamKe;
                }
            } else {
                $grouped->push($this->formatGroupedRow($currentGroup));
                $currentGroup = [
                    'jurnal' => $jurnal,
                    'items' => collect([$jurnal]),
                    'tanggal' => $tanggal,
                    'kelas_id' => $kelasId,
                    'guru_id' => $guruId,
                    'guru_pengganti_id' => $guruPenggantiId,
                    'mapel_id' => $mapelId,
                    'materi' => $materi,
                    'catatan' => $catatan,
                    'status' => $status,
                    'last_jam_ke' => $jamKe,
                    'jam_kes' => $jamKe !== null ? [$jamKe] : [],
                ];
            }
        }

        if ($currentGroup !== null) {
            $grouped->push($this->formatGroupedRow($currentGroup));
        }

        return $grouped;
    }

    /**
     * Format objek hasil grouping untuk dikirim ke view/export.
     */
    protected function formatGroupedRow(array $group): object
    {
        $items = $group['items'];
        $first = $items->first();
        $last = $items->last();

        $firstJam = $first->jadwalPelajaran?->jam;
        $lastJam = $last->jadwalPelajaran?->jam;

        $jamMulaiStr = $firstJam?->jam_mulai ? str_replace(':', '.', substr($firstJam->jam_mulai, 0, 5)) : '';
        $jamSelesaiStr = $lastJam?->jam_selesai ? str_replace(':', '.', substr($lastJam->jam_selesai, 0, 5)) : '';

        $rentangWaktu = ($jamMulaiStr && $jamSelesaiStr) ? "({$jamMulaiStr} - {$jamSelesaiStr})" : '';

        $jamKes = array_filter($group['jam_kes'], fn ($val) => $val !== null);
        $minJamKe = ! empty($jamKes) ? min($jamKes) : null;
        $maxJamKe = ! empty($jamKes) ? max($jamKes) : null;

        $labelJamKe = '-';
        if ($minJamKe !== null && $maxJamKe !== null) {
            if ($minJamKe === $maxJamKe) {
                $labelJamKe = "Jam ke-{$minJamKe}";
            } else {
                $labelJamKe = "Jam ke {$minJamKe} - {$maxJamKe}";
            }
        }

        if ($labelJamKe !== '-' && $rentangWaktu !== '') {
            $labelJam = "{$labelJamKe} {$rentangWaktu}";
        } elseif ($labelJamKe !== '-') {
            $labelJam = $labelJamKe;
        } elseif ($rentangWaktu !== '') {
            $labelJam = $rentangWaktu;
        } else {
            $labelJam = '-';
        }

        return (object) [
            'jurnal' => $first,
            'total_jam' => $items->count(),
            'jam_ke_min' => $minJamKe,
            'jam_ke_max' => $maxJamKe,
            'label_jam' => $labelJam,
            'label_jam_ke' => $labelJamKe,
            'rentang_waktu' => $rentangWaktu,
            'items' => $items,
        ];
    }

    /**
     * Halaman utama Laporan KBM (rekap + ringkasan + filter).
     */
    public function index(Request $request)
    {
        [$query, $mulai, $selesai] = $this->buatQuery($request);

        $allJurnal = (clone $query)->latest('tanggal')->latest('id')->get();
        $groupedCollection = $this->groupJurnalSessions($allJurnal);

        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $currentItems = $groupedCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $daftarJurnal = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $groupedCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        $tahunAjaran = TahunAjaran::aktif();

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

        $allJurnal = (clone $query)->latest('tanggal')->latest('id')->get();
        $daftarJurnal = $this->groupJurnalSessions($allJurnal);

        $ringkasan = $this->hitungRingkasan($query, $mulai, $selesai);
        $tahunAjaran = TahunAjaran::aktif();

        $html = "\xEF\xBB\xBF".view('kurikulum.laporan.excel', array_merge(
            $ringkasan,
            compact('daftarJurnal', 'tahunAjaran', 'mulai', 'selesai')
        ))->render();

        $filename = 'laporan-kbm-'.str_replace('-', '', $mulai).'-'.str_replace('-', '', $selesai).'.xls';

        return response($html)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Halaman cetak / Save PDF — memakai query string filter yang sama
     * dan tombol print browser (window.print()).
     */
    public function printPdf(Request $request)
    {
        [$query, $mulai, $selesai] = $this->buatQuery($request);

        $allJurnal = (clone $query)->latest('tanggal')->latest('id')->get();
        $daftarJurnal = $this->groupJurnalSessions($allJurnal);

        $ringkasan = $this->hitungRingkasan($query, $mulai, $selesai);
        $tahunAjaran = TahunAjaran::aktif();
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
            $bagian[] = 'Tingkat '.$tingkat;
        }
        if ($idKelas = (int) $request->input('id_kelas')) {
            $kelas = Kelas::find($idKelas);
            if ($kelas) {
                $bagian[] = 'Kelas '.$kelas->nama_kelas;
            }
        }
        if ($idGuru = (int) $request->input('id_guru')) {
            $guru = User::find($idGuru);
            if ($guru) {
                $bagian[] = 'Guru: '.$guru->nama;
            }
        }
        if ($idMapel = (int) $request->input('id_mapel')) {
            $mapel = MataPelajaran::find($idMapel);
            if ($mapel) {
                $bagian[] = 'Mapel: '.$mapel->nama_mapel;
            }
        }

        return implode(' · ', $bagian);
    }
}
