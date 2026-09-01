<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Laporan KBM - {{ strtoupper(config('app.name', 'WebJournal Management System')) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f1f5f9;
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 12px;
        }

        .kertas {
            max-width: 900px;
            margin: 24px auto;
            background: #fff;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
        }

        .kop {
            border-bottom: 3px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .kop .nama-sekolah {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .kop .sub-sekolah {
            font-weight: 600;
        }

        h1.judul {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        table.table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        table.table th,
        table.table td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            vertical-align: middle;
        }

        table.table thead th {
            background: #f1f5f9;
            text-align: center;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .ringkasan td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }

        .ringkasan .angka {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .footer-tabel {
            margin-top: 28px;
            display: flex;
            justify-content: flex-end;
        }

        .ttd {
            width: 220px;
            text-align: center;
            font-size: 11px;
        }

        .ttd .spasi {
            height: 72px;
        }

        @media print {
            body {
                background: #fff;
                font-size: 11px;
            }
            .kertas {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                padding: 12px 4px;
                max-width: none;
            }
            .no-print {
                display: none !important;
            }
            table.table {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container no-print mb-4" style="max-width: 900px;">
        <div class="d-flex justify-content-between gap-2 flex-wrap mt-4">
            <a href="{{ route('kurikulum.laporan.index', request()->query()) }}" class="btn btn-outline-secondary rounded-3 px-3 py-2 btn-toolbar-icon fw-semibold">
                <i class="bi bi-arrow-left"></i> Kembali ke Laporan
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm btn-toolbar-icon">
                <i class="bi bi-printer"></i> Cetak / Save PDF
            </button>
        </div>
    </div>

    <div class="kertas">
        <div class="kop text-center">
            <div class="nama-sekolah">{{ strtoupper(config('app.name', 'WebJournal Management System')) }}</div>
            <div class="sub-sekolah">LAPORAN KEGIATAN BELAJAR MENGAJAR (KBM)</div>
            <div class="text-muted" style="font-size: 11px;">
                Periode: {{ $periodeMulai }} s.d. {{ $periodeSelesai }}
                @if(!empty($filterLabel)) &middot; Filter: {{ $filterLabel }} @endif
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="w-full overflow-x-auto">
        <table class="ringkasan mb-4 min-w-full" style="width:100%; font-size: 12px;">
            <tr>
                <td class="text-center" style="width:25%;">
                    <div class="angka" style="color:#16a34a;">{{ number_format($totalJamKBM) }}</div>
                    <div class="text-muted fw-semibold">JAM KBM TERLAKSANA</div>
                </td>
                <td class="text-center" style="width:25%;">
                    <div class="angka" style="color:#0284c7;">{{ number_format($guruHadir) }}</div>
                    <div class="text-muted fw-semibold">GURU HADIR</div>
                    <div style="font-size:10px;">Izin {{ number_format($guruIzin) }} &middot; Sakit {{ number_format($guruSakit) }} &middot; Dinas {{ number_format($guruDinas) }}</div>
                </td>
                <td class="text-center" style="width:25%;">
                    <div class="angka" style="color:#d97706;">{{ number_format($totalJurnalTerisi) }}</div>
                    <div class="text-muted fw-semibold">JURNAL MENGUMPUL</div>
                </td>
                <td class="text-center" style="width:25%;">
                    <div class="angka" style="color:#64748b;">{{ number_format($totalJamKBM - $totalJurnalTerisi) }}</div>
                    <div class="text-muted fw-semibold">JURNAL BELUM TERISI</div>
                </td>
            </tr>
        </table>
        </div>

        {{-- Tabel Data --}}
        <div class="w-full overflow-x-auto">
        <table class="table min-w-full">
            <thead>
                <tr>
                    <th style="width:11%;">No</th>
                    <th style="width:12%;">Tanggal</th>
                    <th style="width:7%;">Jam</th>
                    <th style="width:10%;">Kelas</th>
                    <th style="width:18%;">Guru</th>
                    <th style="width:15%;">Mapel</th>
                    <th style="width:18%;">Materi</th>
                    <th style="width:9%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($daftarJurnal as $idx => $jurnal)
                    @php
                        $jadwal = $jurnal->jadwalPelajaran;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>
                            {{ $jurnal->tanggal->translatedFormat('d/m/Y') }}
                            <div class="text-muted" style="font-size:9px;">{{ $jurnal->tanggal->translatedFormat('l') }}</div>
                        </td>
                        <td class="text-center">ke-{{ $jadwal?->jam?->jam_ke ?? '-' }}</td>
                        <td>{{ $jadwal?->kelas?->nama_kelas ?? '-' }}</td>
                        <td>
                            {{ $jurnal->guru?->nama ?? $jadwal?->guru?->nama ?? '-' }}
                            @if($jurnal->guruPengganti)
                                <div style="font-size:9px; color:#0284c7;">Pengganti: {{ $jurnal->guruPengganti->nama }}</div>
                            @endif
                        </td>
                        <td>{{ $jadwal?->mapel?->nama_mapel ?? '-' }}</td>
                        <td>{{ $jurnal->materi ?: '-' }}</td>
                        <td class="text-center">{!! Str::upper($jurnal->status_kehadiran ?? 'Hadir') !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Tidak ada data pada rentang yang dipilih.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if($totalJamKBM > 0)
            <div class="footer-tabel">
                <div class="ttd">
                    <div>Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }}</div>
                    <div class="spasi"></div>
                    <div class="fw-bold">Waka Kurikulum</div>
                    <div class="text-muted">{{ config('app.name', 'WebJournal Management System') }}</div>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>