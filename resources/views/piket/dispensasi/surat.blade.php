<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Surat Dispen {{ $dispensasi->nomor_surat }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f1f5f9;
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .surat-kop {
            border-bottom: 3px solid #0f172a;
        }

        .surat-kop .nama-sekolah {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }

        .surat-kop .sub-sekolah {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .ttd-siswa img,
        .ttd-guru img {
            max-height: 80px;
            max-width: 100%;
            object-fit: contain;
        }

        .btn-toolbar-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        @media print {
            body {
                background: #fff !important;
            }

            .no-print {
                display: none !important;
            }

            .surat-dispensasi {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }

        @page {
            margin: 1.6cm;
        }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $penandatangan = $dispensasi->approver ?? $dispensasi->guruPiket;
    @endphp

    {{-- Toolbar (tidak tercetak) --}}
    <div class="container no-print mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <a href="{{ route('piket.dispensasi.index') }}" class="btn btn-outline-secondary rounded-3 btn-toolbar-icon">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge {{ $dispensasi->status_badge }} rounded-pill px-3 py-2">{{ $dispensasi->status_label }}</span>
                <button type="button" onclick="window.print()" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm btn-toolbar-icon">
                    <i class="bi bi-printer"></i> Cetak / Save PDF
                </button>
            </div>
        </div>
    </div>

    {{-- Surat --}}
    <div class="container">
        <div class="surat-dispensasi bg-white rounded-4 shadow-sm p-4 p-md-5" style="max-width: 820px; margin: 0 auto;">

            {{-- Kop Surat --}}
            <div class="surat-kop text-center pb-3 mb-4">
                <div class="nama-sekolah">{{ strtoupper(config('app.name', 'WebJournal Management System')) }}</div>
                <div class="sub-sekolah">SISTEM DISPENSASI SISWA SEKOLAH</div>
                <div class="text-muted small">SURAT DISPENSASI DIGITAL</div>
            </div>

            {{-- Judul, Nomor & Status --}}
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1" style="letter-spacing: 0.02em;">SURAT DISPENSASI</h3>
                <div class="text-muted">
                    Nomor: <span class="fw-semibold text-dark">{{ $dispensasi->nomor_surat }}</span>
                </div>
            </div>

            <p class="mb-2">
                Guru Piket hari {{ $dispensasi->tanggal->translatedFormat('l, d F Y') }} menerangkan bahwa siswa di bawah ini:
            </p>

            {{-- Data Siswa & Pengajuan --}}
            <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-sm table-borderless align-middle mb-4 min-w-full" style="max-width: 560px;">
                <tbody>
                    <tr>
                        <td class="text-muted" style="width: 36%;">Nama Siswa</td>
                        <td style="width: 4%;">:</td>
                        <td class="fw-bold text-dark">{{ $dispensasi->siswa->nama ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">NISN / NIS</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">{{ $dispensasi->siswa->nisn ?? '-' }} / {{ $dispensasi->siswa->nis ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Kelas</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">{{ $dispensasi->siswa?->kelas?->nama ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Hari / Tanggal</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">{{ $dispensasi->tanggal->translatedFormat('l, d F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Jam KBM yang Ditinggalkan</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">{{ $dispensasi->jam_ke_label }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Alasan Kegiatan</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">{{ $dispensasi->alasan }}</td>
                    </tr>
                </tbody>
            </table>
            </div>

            <p class="mb-5">
                Diperkenankan untuk tidak mengikuti kegiatan belajar mengajar pada jam tersebut di atas
                dengan alasan <strong>{{ $dispensasi->alasan }}</strong>.
                @if($dispensasi->isApproved())
                    Sesuai persetujuan yang telah disahkan, absensi siswa pada jam terkait otomatis
                    tercatat sebagai <strong>Dispen</strong> pada jurnal mengajar.
                @endif
                Demikian surat dispensasi ini dibuat dengan sebenarnya untuk digunakan sebagaimana mestinya.
            </p>

            {{-- TTD Guru Piket (Penyetuju) & Siswa (Pemohon) --}}
            <div class="row mt-5 align-items-end">
                <div class="col-6">
                    <div class="text-center" style="max-width: 240px; margin: 0 auto;">
                        <div class="text-muted small mb-1">Tanda Tangan Digital — Guru Piket (Penyetuju)</div>
                        @if($dispensasi->has_ttd_guru)
                            <div class="ttd-guru border rounded-3 p-2 bg-light-subtle mb-2" style="display: inline-block;">
                                <img src="{{ $dispensasi->ttd_guru_url }}" alt="TTD Guru Piket">
                            </div>
                        @else
                            <div style="height: 74px;"></div>
                        @endif
                        <div class="fw-bold text-dark" style="text-transform: uppercase;">{{ $penandatangan->nama ?? '-' }}</div>
                        <div class="text-muted small">NIP. {{ $penandatangan->nip ?? '-' }}</div>
                        <div class="text-muted small fst-italic mt-1">
                            <i class="bi bi-patch-check-fill text-primary me-1"></i>
                            Disetujui {{ $dispensasi->approved_at?->translatedFormat('d M Y, H:i') }}
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-center" style="max-width: 240px; margin: 0 auto;">
                        <div class="text-muted small mb-1">Tanda Tangan Siswa (Pemohon)</div>
                        @if($dispensasi->has_ttd)
                            <div class="ttd-siswa border rounded-3 p-2 bg-light-subtle mb-2" style="display: inline-block;">
                                <img src="{{ $dispensasi->ttd_url }}" alt="TTD Siswa">
                            </div>
                        @else
                            <div style="height: 74px;"></div>
                        @endif
                        <div class="fw-bold text-dark mt-2" style="text-transform: uppercase; text-decoration: underline;">{{ $dispensasi->siswa->nama ?? '-' }}</div>
                        <div class="text-muted small">NISN. {{ $dispensasi->siswa->nisn ?? '-' }}</div>
                    </div>
                </div>
            </div>

            </div>
    </div>
</body>
</html>