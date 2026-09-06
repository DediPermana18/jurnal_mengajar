<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kedisiplinan KBM Guru - Bulan {{ $namaBulan }} {{ $tahun }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        .kop-surat {
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .kop-instansi {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .kop-sekolah {
            font-size: 16pt;
            font-weight: 900;
            text-transform: uppercase;
        }
        .kop-alamat {
            font-size: 9.5pt;
            margin-top: 2px;
        }
        .report-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .report-subtitle {
            text-align: center;
            font-size: 10.5pt;
            margin-bottom: 18px;
        }
        table.table-report {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 20px;
        }
        table.table-report th, table.table-report td {
            border: 1px solid #000;
            padding: 5px 7px;
            vertical-align: middle;
        }
        table.table-report th {
            background-color: #f2f2f2 !important;
            font-weight: bold;
            text-align: center;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .signature-section {
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 260px;
        }
        .signature-space {
            height: 75px;
        }
        .summary-box-print {
            border: 1px solid #ccc;
            padding: 8px 12px;
            font-size: 9.5pt;
            margin-bottom: 15px;
            background: #fafafa;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            table.table-report th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    {{-- Toolbar Print (Hidden saat dicetak) --}}
    <div class="no-print mb-4 p-3 bg-light border rounded-3 d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <h6 class="mb-0 fw-bold text-dark">Laporan Kedisiplinan & KBM Guru</h6>
            <small class="text-muted">Siap dicetak atau disimpan sebagai file PDF</small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-3 px-3 fw-semibold">
                <i class="bi bi-printer-fill me-1"></i> Cetak Dokumen / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-sm rounded-3 px-3">
                Tutup Jendela
            </button>
        </div>
    </div>

    {{-- KOP SURAT --}}
    <div class="kop-surat">
        <div class="kop-instansi">PEMERINTAH DAERAH PROVINSI / KEMENTERIAN AGAMA</div>
        <div class="kop-sekolah">SMK / SMA NEGERI INDONESIA MANDIRI</div>
        <div class="kop-alamat">
            Jl. Pendidikan No. 45, Kompleks Akademik Terpadu &nbsp;|&nbsp; Telp: (021) 7890123 &nbsp;|&nbsp; Website: www.school.id &nbsp;|&nbsp; Email: info@school.id
        </div>
    </div>

    {{-- JUDUL LAPORAN --}}
    <div class="report-title">
        LAPORAN BULANAN KEDISIPLINAN & KINERJA MENGAJAR GURU
    </div>
    <div class="report-subtitle">
        Bulan: <strong>{{ $namaBulan }} {{ $tahun }}</strong> &nbsp;|&nbsp; 
        Tahun Ajaran: <strong>{{ $tahunAktif ? $tahunAktif->tahun . ' (' . $tahunAktif->semester . ')' : 'Aktif' }}</strong>
    </div>

    {{-- RINGKASAN DATA --}}
    <div class="summary-box-print">
        <div class="row">
            <div class="col-3">
                Total Guru Terdaftar: <strong>{{ count($dataRekap) }} Orang</strong>
            </div>
            <div class="col-3">
                Total JP Wajib: <strong>{{ $grandTotalJpWajib }} JP</strong>
            </div>
            <div class="col-3">
                JP Terealisasi: <strong>{{ $grandTotalJpTerealisasi }} JP</strong> (Cover: {{ $grandTotalJpCover }} JP)
            </div>
            <div class="col-3 text-end">
                Rata-rata Kedisiplinan: <strong>{{ $rataRataKedisiplinan }}%</strong>
            </div>
        </div>
    </div>

    {{-- TABEL LAPORAN --}}
    <table class="table-report">
        <thead>
            <tr>
                <th style="width: 35px;">No</th>
                <th>Nama Guru & Gelar</th>
                <th style="width: 120px;">NIP</th>
                <th>Mata Pelajaran</th>
                <th style="width: 65px;">JP Wajib</th>
                <th style="width: 65px;">JP Hadir</th>
                <th style="width: 65px;">JP Cover</th>
                <th style="width: 65px;">JP Izin</th>
                <th style="width: 65px;">JP Kosong</th>
                <th style="width: 80px;">Disiplin (%)</th>
                <th style="width: 100px;">Kinerja</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dataRekap as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><strong>{{ $item->guru->nama }}</strong></td>
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
                    <td colspan="11" class="text-center py-3">Tidak ada data guru untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($dataRekap) > 0)
            <tfoot>
                <tr style="font-weight: bold; background-color: #f2f2f2;">
                    <td colspan="4" class="text-end">TOTAL KESELURUHAN:</td>
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

    {{-- CATATAN LAPORAN --}}
    <div style="font-size: 9.5pt; margin-bottom: 25px;">
        <em>* Catatan: JP (Jam Pelajaran) dihitung berdasarkan plotting jadwal resmi kurikulum. JP Terealisasi diakui dari pengisian Jurnal Mengajar digital tepat waktu / terverifikasi.</em>
    </div>

    {{-- TANDA TANGAN (KEPALA SEKOLAH & WAKA SDM) --}}
    <div class="signature-section d-flex justify-content-between">
        <div class="signature-box">
            <div>Mengetahui,</div>
            <div class="fw-bold">Kepala Sekolah</div>
            <div class="signature-space"></div>
            <div class="fw-bold text-decoration-underline">{{ $kepsek?->nama ?? 'Drs. H. Mulyadi, M.Pd.' }}</div>
            <div>NIP. {{ $kepsek?->nip ?? '197001011995031001' }}</div>
        </div>

        <div class="signature-box">
            <div>Dibuat di: Kota Terkait</div>
            <div>Tanggal: {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</div>
            <div class="fw-bold">Waka SDM / Kepegawaian</div>
            <div class="signature-space"></div>
            <div class="fw-bold text-decoration-underline">{{ $wakaSdm?->nama ?? 'Drs. Supriyanto, M.M.' }}</div>
            <div>NIP. {{ $wakaSdm?->nip ?? '197804152005011004' }}</div>
        </div>
    </div>

</body>
</html>
