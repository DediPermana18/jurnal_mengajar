<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Surat Dispen Rombongan {{ $kolektif->nomor_surat }}</title>
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

        .btn-toolbar-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ttd-img {
            max-height: 70px;
            max-width: 100%;
            object-fit: contain;
        }

        .surat-dispensasi table.ttd-siswa-table img {
            max-height: 70px;
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
                padding: 0.5rem 0 !important;
            }
        }

        @page {
            margin: 1.6cm;
        }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $piket = $kolektif->guruPiket ?? $user;
        $siswaItems = $kolektif->siswaItems;
        $backUser = $user;
        $backUrl = $backUser && ($backUser->isWakaKesiswaan()
                || ($backUser->hasActiveRole() && $backUser->activeRole() === 'waka_kesiswaan'))
            ? route('waka-kesiswaan.dispensasi.approval.index')
            : route('piket.dispensasi.index');
    @endphp

    {{-- Toolbar (tidak tercetak) --}}
    <div class="container no-print mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary rounded-3 btn-toolbar-icon">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge {{ $kolektif->status_badge }} rounded-pill px-3 py-2">{{ $kolektif->status_label }}</span>
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
                <div class="text-muted small">SURAT DISPENSASI DIGITAL (ROMBONGAN / KOLEKTIF)</div>
            </div>

            {{-- Judul, Nomor & Status --}}
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1" style="letter-spacing: 0.02em;">SURAT DISPENSASI</h3>
                <div class="text-muted">
                    Nomor: <span class="fw-semibold text-dark">{{ $kolektif->nomor_surat }}</span>
                    <span class="mx-1">•</span>
                    Jumlah Siswa: <span class="fw-semibold text-dark">{{ $siswaItems->count() }} orang</span>
                </div>
            </div>

            <p class="mb-2">
                Guru Piket hari {{ $kolektif->tanggal->translatedFormat('l, d F Y') }} menerangkan bahwa para siswa di bawah ini:
            </p>

            {{-- Data Pengajuan (berbagi) --}}
            <div class="table-responsive w-100 mb-4">
                <table class="table table-sm table-borderless align-middle mb-0" style="max-width: 620px;">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 36%;">Hari / Tanggal</td>
                            <td style="width: 4%;">:</td>
                            <td class="fw-semibold text-dark">{{ $kolektif->tanggal->translatedFormat('l, d F Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu Pembuatan</td>
                            <td>:</td>
                            <td class="fw-semibold text-dark">{{ $kolektif->created_at ? $kolektif->created_at->format('H:i') . ' WIB' : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tipe Dispensasi</td>
                            <td>:</td>
                            <td class="fw-semibold text-dark">{{ $kolektif->tipe_dispen_label }}</td>
                        </tr>
                        @if(!$kolektif->isTipeMasuk())
                            <tr>
                                <td class="text-muted">Jam Ditinggalkan</td>
                                <td>:</td>
                                <td class="fw-semibold text-dark">
                                    {{ $kolektif->jam_ke ? $kolektif->jam_ke_label : '-' }}
                                    @if($kolektif->jadwal && $kolektif->jadwal->mapel)
                                        — {{ $kolektif->jadwal->mapel->nama }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jam Berangkat / Keluar</td>
                                <td>:</td>
                                <td class="fw-semibold text-dark">
                                    @if(!empty($kolektif->jam_keluar_jp))
                                        Jam Ke-{{ $kolektif->jam_keluar_jp }}
                                        @php
                                            $jamKeluarDetail = \App\Models\JamPelajaran::where('jam_ke', $kolektif->jam_keluar_jp)->orderBy('jam_mulai')->first();
                                        @endphp
                                        @if($jamKeluarDetail)
                                            ({{ substr($jamKeluarDetail->jam_mulai, 0, 5) }} WIB)
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @if($kolektif->tidak_kembali_hari_ini)
                                <tr>
                                    <td class="text-muted">Rencana Kembali</td>
                                    <td>:</td>
                                    <td class="fw-semibold text-dark">Tidak kembali hari ini (izin hingga pulang)</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="text-muted">Rencana Kembali</td>
                                    <td>:</td>
                                    <td class="fw-semibold text-dark">
                                        @if($kolektif->jam_kembali_jp)
                                            Jam Ke-{{ $kolektif->jam_kembali_jp }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @else
                            <tr>
                                <td class="text-muted">Boleh Masuk Mulai JP</td>
                                <td>:</td>
                                <td class="fw-semibold text-dark">
                                    @if($kolektif->jam_masuk_jp)
                                        Jam Ke-{{ $kolektif->jam_masuk_jp }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Alasan Kegiatan</td>
                            <td>:</td>
                            <td class="fw-semibold text-dark">{{ $kolektif->alasan }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Daftar Siswa + TTD Digital masing-masing --}}
            <div class="table-responsive w-100 mb-4">
                <table class="table table-sm table-bordered align-middle mb-0 ttd-siswa-table">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th style="width: 40px;">No</th>
                            <th>Nama Siswa</th>
                            <th style="width: 110px;">NISN / NIS</th>
                            <th style="width: 130px;">Kelas</th>
                            @if($kolektif->isTipeMasuk())
                                <th style="width: 120px;">Jam Kedatangan Gerbang</th>
                            @endif
                            <th style="width: 170px;">Tanda Tangan Siswa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($siswaItems as $i => $item)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $item->siswa->nama ?? '-' }}</td>
                                <td>{{ ($item->siswa->nisn ?? '-') . ' / ' . ($item->siswa->nis ?? '-') }}</td>
                                <td>{{ $item->siswa?->kelas?->nama_lengkap ?? $item->siswa?->kelas?->nama_kelas ?? '-' }}</td>
                                @if($kolektif->isTipeMasuk())
                                    <td class="text-center">
                                        @if($item->catatanTerlambat && $item->catatanTerlambat->jam_masuk)
                                            <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                                                {{ $item->catatanTerlambat->jam_masuk->format('H:i') }} WIB
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-center">
                                    @if(!empty($item->ttd_siswa))
                                        <img src="{{ $item->ttd_siswa }}" class="ttd-img" alt="TTD {{ $item->siswa->nama ?? 'Siswa' }}">
                                    @else
                                        <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Belum TTD)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $kolektif->isTipeMasuk() ? 6 : 5 }}" class="text-center text-muted small">Tidak ada siswa pada pengajuan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mb-5">
                Para siswa di atas diperkenankan untuk tidak mengikuti kegiatan belajar mengajar pada jam tersebut di atas
                dengan alasan <strong>{{ $kolektif->alasan }}</strong>, dan telah menandatangani surat dispensasi ini secara
                digital sebagai konfirmasi persetujuan. Demikian surat dispensasi ini dibuat dengan sebenarnya untuk digunakan
                sebagaimana mestinya.
            </p>

            {{-- TTD 2 Kolom: Guru Piket & Waka Kesiswaan --}}
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; gap: 40px; text-align: center;">
                {{-- TTD Guru Piket --}}
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">TTD Guru Piket</p>
                    <div style="height: 80px; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px;">
                        @if($kolektif->ttd_guru_url)
                            <img src="{{ $kolektif->ttd_guru_url }}" style="max-height: 70px; width: auto; max-width: 100%; object-fit: contain;" alt="TTD Piket">
                        @else
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Belum TTD)</span>
                        @endif
                    </div>
                    <p style="font-weight: bold; font-size: 14px; margin-top: 8px;">{{ $piket->nama ?? $piket->name ?? '-' }}</p>
                    <p style="font-size: 12px; color: #6b7280;">NIP. {{ $piket->nip ?? '-' }}</p>
                </div>

                {{-- TTD Waka Kesiswaan --}}
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">TTD Waka Kesiswaan</p>
                    <div style="height: 80px; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px;">
                        <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Menunggu Approval Waka)</span>
                    </div>
                    <p style="font-weight: bold; font-size: 14px; margin-top: 8px;">{{ \App\Models\PengaturanJadwal::getSetting()?->nama_waka_kesiswaan ?? '. . .' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Auto print saat dibuka dengan ?print=1 --}}
    @if(request()->has('print'))
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>