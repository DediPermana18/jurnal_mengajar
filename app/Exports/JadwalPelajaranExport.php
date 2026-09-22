<?php

namespace App\Exports;

use App\Models\JadwalPelajaran;
use App\Models\Scopes\TestingDataScope;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class JadwalPelajaranExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?int $tahunAjaranId;

    protected int $isTestingData;

    public function __construct(?int $tahunAjaranId = null, int $isTestingData = 0)
    {
        $this->tahunAjaranId = $tahunAjaranId;
        $this->isTestingData = $isTestingData;
    }

    public function collection(): Enumerable
    {
        $tahunId = $this->tahunAjaranId;
        if (! $tahunId) {
            $activeTahun = TahunAjaran::withoutGlobalScope(TestingDataScope::class)
                ->where('is_active', true)
                ->first();
            $tahunId = $activeTahun?->id;
        }

        $jadwals = JadwalPelajaran::withoutGlobalScope(TestingDataScope::class)
            ->where('is_testing_data', $this->isTestingData)
            ->whereHas('kelas')
            ->when($tahunId, fn ($q) => $q->where('id_tahun_ajaran', $tahunId))
            ->with(['kelas', 'mataPelajaran', 'guru', 'ruangan', 'jamPelajaran'])
            ->get();

        // Mengelompokkan slot jam berurutan yang memiliki Mapel, Guru, dan Ruangan sama
        $grouped = [];

        foreach ($jadwals as $j) {
            $tingkat   = trim((string) ($j->kelas?->tingkat ?? ''));
            $namaKelas = trim((string) ($j->kelas?->nama_kelas ?? ''));

            if (! empty($namaKelas)) {
                $hasPrefix = preg_match('/^(X|XI|XII|10|11|12)\s+/i', $namaKelas);
                if (! empty($tingkat) && ! $hasPrefix && ! str_starts_with(strtoupper($namaKelas), strtoupper($tingkat))) {
                    $kelasNama = trim($tingkat.' '.$namaKelas);
                } else {
                    $kelasNama = $namaKelas;
                }
            } else {
                $kelasNama = 'Kelas Tidak Ditemukan';
            }

            $hari      = $j->hari;
            $groupId   = $j->group_id;
            $mapelId   = $j->id_mapel;
            $guruId    = $j->id_guru;
            $ruangId   = $j->id_ruangan;
            $jamKe     = $j->jamPelajaran->jam_ke ?? null;

            if ($jamKe === null) {
                continue;
            }

            // Kunci pengelompokan
            $groupKey = $groupId !== null && $groupId !== ''
                ? "{$j->id_kelas}|{$hari}|group:{$groupId}"
                : "{$j->id_kelas}|{$hari}|mapel:{$mapelId}|guru:{$guruId}|ruang:{$ruangId}";

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'kelas'  => $kelasNama,
                    'hari'   => $hari,
                    'mapel'  => $j->mataPelajaran?->nama_mapel ?? '-',
                    'guru'   => $j->guru?->nama ?? '-',
                    'ruang'  => $j->ruangan?->nama_ruangan ?? $j->ruangan?->kode_ruangan ?? '',
                    'jam_kes' => [],
                ];
            }

            $grouped[$groupKey]['jam_kes'][] = $jamKe;
        }

        $rows = new Collection();

        foreach ($grouped as $g) {
            sort($g['jam_kes']);
            $jamStr = implode('.', array_unique($g['jam_kes']));

            $rows->push((object) [
                'kelas' => $g['kelas'],
                'hari'  => $g['hari'],
                'jam'   => $jamStr,
                'mapel' => $g['mapel'],
                'guru'  => $g['guru'],
                'ruang' => $g['ruang'],
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Kelas',
            'Hari',
            'Jam',
            'MataPelajaran',
            'Guru',
            'Ruang',
        ];
    }

    public function map($row): array
    {
        return [
            $row->kelas,
            $row->hari,
            $row->jam,
            $row->mapel,
            $row->guru,
            $row->ruang,
        ];
    }
}
