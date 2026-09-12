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
        $piket = $dispensasi->piket ?? $dispensasi->guruPiket ?? auth()->user();
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
                    <!-- Baris Jam Pembuatan Surat -->
                    <tr>
                        <td class="text-muted">Waktu Pembuatan</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">
                            {{ $dispensasi->created_at ? $dispensasi->created_at->format('H:i') . ' WIB' : '-' }}
                        </td>
                    </tr>

                    <!-- Baris Jam Berangkat / Keluar Sekolah -->
                    <tr>
                        <td class="text-muted">Jam Berangkat / Keluar</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">
                            @if(!empty($dispensasi->jam_keluar_jp))
                                Jam Ke-{{ $dispensasi->jam_keluar_jp }}
                                @if(isset($jamKeluarDetail) && $jamKeluarDetail)
                                    ({{ substr($jamKeluarDetail->jam_mulai, 0, 5) }} WIB)
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    {{-- Baris Rencana Jam Kembali (Part 3) --}}
                    @if(!empty($dispensasi->jam_kembali_jp) || $dispensasi->isTidakKembaliHariIni())
                    <tr>
                        <td class="text-muted">Rencana Kembali</td>
                        <td>:</td>
                        <td class="fw-semibold text-dark">
                            @if($dispensasi->isTidakKembaliHariIni())
                                Tidak kembali hari ini (izin hingga pulang)
                            @else
                                Jam Ke-{{ $dispensasi->jam_kembali_jp }}
                                @php
                                    $jamKembaliDetail = $dispensasi->jam_kembali_jp
                                        ? \App\Models\JamPelajaran::where('jam_ke', $dispensasi->jam_kembali_jp)->orderBy('jam_mulai')->first()
                                        : null;
                                @endphp
                                @if($jamKembaliDetail)
                                    ({{ substr($jamKembaliDetail->jam_mulai, 0, 5) }} WIB)
                                @endif
                                @if($dispensasi->isKembali())
                                    — <span class="text-success fw-bold">Kembali {{ $dispensasi->kembali_at->format('H:i') }}</span>
                                @elseif($dispensasi->isMangkir())
                                    — <span class="text-danger fw-bold">MANGKIR / BOLOS</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endif
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
                @if($dispensasi->isApproved() && !$dispensasi->isDibatalkan() && !$dispensasi->isExpired())
                    Sesuai persetujuan yang telah disahkan, absensi siswa pada jam terkait otomatis
                    tercatat sebagai <strong>Dispen</strong> pada jurnal mengajar.
                @elseif($dispensasi->isMangkir())
                    Siswa tidak kembali sesuai Rencana Jam Kembali sehingga surat ini tercatat sebagai
                    <strong>Mangkir / Bolos</strong> dan absensi JP terkait otomatis menjadi <strong>Alfa</strong>.
                @endif
                Demikian surat dispensasi ini dibuat dengan sebenarnya untuk digunakan sebagaimana mestinya.
            </p>


                        <!-- KODE TTD 3 KOLOM SEJAJAR (JANGAN SAMPAI DITIMPA COPILOT LAGI) -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 32px; gap: 16px; text-align: center;">
                
                <!-- TTD Siswa -->
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">TTD Siswa</p>
                    <div style="height: 80px; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px;">
                        @if(!empty($dispensasi->ttd_siswa))
                            <img src="{{ $dispensasi->ttd_siswa }}" style="max-height: 70px; width: auto; max-width: 100%; object-fit: contain;" alt="TTD Siswa">
                        @else
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Belum TTD)</span>
                        @endif
                    </div>
                    <p style="font-weight: bold; font-size: 14px; text-decoration: underline; margin-top: 8px;">{{ $dispensasi->siswa->nama ?? 'Alya Maharani' }}</p>
                    <p style="font-size: 12px; color: #6b7280;">NISN. {{ $dispensasi->siswa->nisn ?? '-' }}</p>
                </div>

                <!-- TTD Guru Piket -->
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">TTD Guru Piket</p>
                    <div style="height: 80px; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px;">
                        @if(!empty($dispensasi->ttd_piket))
                            <img src="{{ $dispensasi->ttd_piket }}" style="max-height: 70px; width: auto; max-width: 100%; object-fit: contain;" alt="TTD Piket">
                        @elseif(!empty($dispensasi->ttd_guru))
                            <img src="{{ $dispensasi->ttd_guru }}" style="max-height: 70px; width: auto; max-width: 100%; object-fit: contain;" alt="TTD Piket">
                        @else
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Belum TTD)</span>
                        @endif
                    </div>
                    <p style="font-weight: bold; font-size: 14px; margin-top: 8px;">{{ $dispensasi->piket->nama ?? $dispensasi->piket->name ?? 'Eko Prasetyo, S.Sn., M.Ds.' }}</p>
                    <p style="font-size: 12px; color: #6b7280;">NIP. {{ $dispensasi->piket->nip ?? '-' }}</p>
                </div>

                <!-- TTD Waka Kesiswaan -->
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <p style="font-size: 12px; font-weight: 600; margin-bottom: 8px;">TTD Waka Kesiswaan</p>
                    <div style="height: 80px; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 4px;">
                        @if(!empty($dispensasi->ttd_waka))
                            <img src="{{ $dispensasi->ttd_waka }}" style="max-height: 70px; width: auto; max-width: 100%; object-fit: contain;" alt="TTD Waka">
                        @elseif(!in_array($dispensasi->status, [
                            \App\Models\DispensasiSiswa::STATUS_PENDING,
                            \App\Models\DispensasiSiswa::STATUS_PENDING_WAKA,
                            \App\Models\DispensasiSiswa::STATUS_DISETUJUI,
                            \App\Models\DispensasiSiswa::STATUS_APPROVED,
                            \App\Models\DispensasiSiswa::STATUS_FINAL,
                        ], true))
                            <span style="font-size: 12px; color: #dc2626; font-style: italic; font-weight: 600;">({{ $dispensasi->status_label }})</span>
                        @else
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">(Menunggu Approval)</span>
                        @endif
                    </div>
                    <p style="font-weight: bold; font-size: 14px; margin-top: 8px;">{{ $wakaNama ?? '. . .' }}</p>
                    @if(!empty($wakaNip))
                        <p style="font-size: 12px; color: #6b7280;">NIP. {{ $wakaNip }}</p>
                    @endif
                    <p style="font-size: 12px; color: #6b7280;">Waka Kesiswaan</p>
                </div>

            </div>

            </div>
    </div>

    {{-- Auto print saat dibuka dengan ?print=1 (dipakai tombol "Cetak PDF" di portal Waka Kesiswaan) --}}
    @if(request()->has('print'))
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>