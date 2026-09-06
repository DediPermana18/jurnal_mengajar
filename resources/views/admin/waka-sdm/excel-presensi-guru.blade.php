<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kedisiplinan KBM Guru - Bulan {{ $namaBulan }} {{ $tahun }}</title>
    <style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        .title { font-size: 14pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 11pt; text-align: center; margin-bottom: 15px; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #1e293b; color: #ffffff; border: 1px solid #000000; padding: 6px 8px; text-align: center; }
        td { border: 1px solid #000000; padding: 5px 8px; vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .bg-total { background-color: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">LAPORAN REKAPITULASI KEDISIPLINAN & REALISASI KBM GURU</div>
    <div class="subtitle">
        Bulan: {{ $namaBulan }} {{ $tahun }} &nbsp;|&nbsp; Tahun Ajaran: {{ $tahunAktif ? $tahunAktif->tahun . ' (' . $tahunAktif->semester . ')' : '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th style="width: 180px;">Nama Guru</th>
                <th style="width: 130px;">NIP</th>
                <th style="width: 180px;">Mata Pelajaran</th>
                <th style="width: 80px;">JP Wajib</th>
                <th style="width: 90px;">JP Realisasi</th>
                <th style="width: 80px;">JP Cover</th>
                <th style="width: 80px;">JP Izin</th>
                <th style="width: 80px;">JP Kosong</th>
                <th style="width: 100px;">Kedisiplinan (%)</th>
                <th style="width: 110px;">Kategori Kinerja</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dataRekap as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item->guru->nama }}</td>
                    <td class="text-center">{{ $item->guru->nip ?? '-' }}</td>
                    <td>{{ $item->mapel }}</td>
                    <td class="text-center">{{ $item->jpWajib }}</td>
                    <td class="text-center">{{ $item->jpTerealisasi }}</td>
                    <td class="text-center">{{ $item->jpCover }}</td>
                    <td class="text-center">{{ $item->jpIzin }}</td>
                    <td class="text-center">{{ $item->jpAlpha }}</td>
                    <td class="text-center fw-bold">{{ $item->persentase }}%</td>
                    <td class="text-center">{{ $item->kategoriKinerja }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">Tidak ada data guru untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($dataRekap) > 0)
            <tfoot>
                <tr class="bg-total">
                    <td colspan="4" class="text-right fw-bold">TOTAL KESELURUHAN:</td>
                    <td class="text-center">{{ $grandTotalJpWajib }}</td>
                    <td class="text-center">{{ $grandTotalJpTerealisasi }}</td>
                    <td class="text-center">{{ $grandTotalJpCover }}</td>
                    <td class="text-center">{{ $grandTotalJpIzin }}</td>
                    <td class="text-center">{{ $grandTotalJpAlpha }}</td>
                    <td class="text-center">{{ $rataRataKedisiplinan }}%</td>
                    <td class="text-center">-</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
