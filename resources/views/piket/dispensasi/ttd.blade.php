<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Tanda Tangan Siswa - Surat Dispen {{ $dispensasi->nomor_surat }}</title>
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
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }

        .surat-kop .sub-sekolah {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .ttd-img img {
            max-height: 80px;
            max-width: 100%;
            object-fit: contain;
        }

        .btn-toolbar-icon {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $penandatangan = $dispensasi->approver ?? $dispensasi->guruPiket;
    @endphp

    {{-- Toolbar --}}
    <div class="container mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <a href="{{ route('piket.dispensasi.index', ['tanggal' => $dispensasi->tanggal->toDateString()]) }}" class="btn btn-outline-secondary rounded-3 btn-toolbar-icon">
                    <i class="bi bi-arrow-left"></i> Kembali ke Daftar
                </a>
            </div>
            <span class="badge {{ $dispensasi->status_badge }} rounded-pill px-3 py-2">{{ $dispensasi->status_label }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="container mb-3">
            <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="container mb-3">
            <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    <div class="container">
        {{-- Info pengisian --}}
        <div class="alert bg-primary-subtle text-primary border-0 rounded-4 mb-4 d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-pencil-square fs-5 mt-1"></i>
            <div>
                <strong>Konfirmasi Akhir — Tanda Tangan Siswa (Pemohon)</strong><br>
                <span class="small">Dispensasi telah disetujui (ACC) oleh Guru Piket. Minta siswa menggoreskan
                tanda tangannya di kanvas di bawah ini, lalu klik <strong>Simpan Tanda Tangan</strong> untuk melengkapi surat.</span>
            </div>
        </div>

        <div class="row g-4">
            {{-- Kolom Kiri: Surat preview --}}
            <div class="col-lg-7">
                <div class="bg-white rounded-4 shadow-sm p-4" style="min-height: 100%;">
                    {{-- Kop Surat --}}
                    <div class="surat-kop text-center pb-3 mb-3">
                        <div class="nama-sekolah">{{ strtoupper(config('app.name', 'WebJournal Management System')) }}</div>
                        <div class="sub-sekolah">SISTEM DISPENSASI SISWA SEKOLAH</div>
                        <div class="text-muted small">SURAT DISPENSASI DIGITAL</div>
                    </div>

                    <div class="text-center mb-3">
                        <h5 class="fw-bold mb-0" style="letter-spacing: 0.02em;">SURAT DISPENSASI</h5>
                        <div class="text-muted small">Nomor: <span class="fw-semibold text-dark">{{ $dispensasi->nomor_surat }}</span></div>
                    </div>

                    <p class="mb-2 small">
                        Guru Piket hari {{ $dispensasi->tanggal->translatedFormat('l, d F Y') }} menerangkan bahwa siswa di bawah ini:
                    </p>

                    <div class="table-responsive w-full overflow-x-auto">
                    <table class="table table-sm table-borderless align-middle mb-3 small min-w-full" style="max-width: 480px;">
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

                    {{-- TTD blocks preview: Guru Piket kiri (tetap), Siswa kanan (kanvas) --}}
                    <div class="row align-items-end mt-4">
                        <div class="col-6">
                            <div class="text-center" style="max-width: 220px; margin: 0 auto;">
                                <div class="text-muted small mb-1">Tanda Tangan Digital — Guru Piket (Penyetuju)</div>
                                @if($dispensasi->has_ttd_guru)
                                    <div class="ttd-img border rounded-3 p-2 bg-light-subtle mb-2" style="display: inline-block;">
                                        <img src="{{ $dispensasi->ttd_guru_url }}" alt="TTD Guru Piket">
                                    </div>
                                @else
                                    <div style="height: 70px;"></div>
                                @endif
                                <div class="fw-bold text-dark" style="text-transform: uppercase;">{{ $penandatangan->nama ?? '-' }}</div>
                                <div class="text-muted small">NIP. {{ $penandatangan->nip ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center" style="max-width: 220px; margin: 0 auto;">
                                <div class="text-muted small mb-1">Tanda Tangan Siswa (Pemohon)</div>
                                @if($dispensasi->has_ttd)
                                    <div class="ttd-img border rounded-3 p-2 bg-light-subtle mb-2" style="display: inline-block;">
                                        <img src="{{ $dispensasi->ttd_url }}" alt="TTD Siswa">
                                    </div>
                                    <div class="text-success small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>Sudah ditandatangani</div>
                                @else
                                    <div class="text-warning small fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Menunggu TTD siswa</div>
                                @endif
                                <div class="fw-bold text-dark mt-2" style="text-transform: uppercase; text-decoration: underline;">{{ $dispensasi->siswa->nama ?? '-' }}</div>
                                <div class="text-muted small">NISN. {{ $dispensasi->siswa->nisn ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Canvas TTD Siswa --}}
            <div class="col-lg-5">
                <form action="{{ route('piket.dispensasi.ttd-save', $dispensasi->id) }}" method="POST" id="formTtd">
                    @csrf
                    <div class="bg-white rounded-4 shadow-sm p-4 sticky-top" style="top: 1rem;">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-pencil me-1"></i>Tanda Tangan Siswa</h5>
                        <p class="text-muted small mb-3">Goreskan tanda tangan siswa (pemohon) pada kotak di bawah ini menggunakan mouse / layar sentuh.</p>

                        <div class="border rounded-3 overflow-hidden mb-3">
                            <canvas id="canvasTtd" width="600" height="260"
                                    style="width: 100%; height: auto; display: block; background: #fff; touch-action: none; cursor: crosshair;">
                                Browser Anda tidak mendukung Canvas.
                            </canvas>
                            <div class="d-flex justify-content-between align-items-center bg-light-subtle px-3 py-2">
                                <span class="text-muted small"><i class="bi bi-pencil me-1"></i>Tanda tangan siswa</span>
                                <button type="button" id="btnClear" class="btn btn-sm btn-outline-danger rounded-3">
                                    <i class="bi bi-eraser me-1"></i> Hapus
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="ttd_siswa" id="ttdSiswa" value="">

                        @if($dispensasi->has_ttd)
                            <div class="alert alert-success rounded-3 py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                <span>Tanda tangan siswa sudah terisi. Anda dapat menggantinya lalu menyimpan ulang.</span>
                            </div>
                        @endif

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm flex-grow-1">
                                <i class="bi bi-check-lg me-1"></i> Simpan Tanda Tangan
                            </button>
                            <a href="{{ route('piket.dispensasi.surat', $dispensasi->id) }}" target="_blank" class="btn btn-outline-dark rounded-3" title="Lihat surat">
                                <i class="bi bi-file-earmark-text"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('canvasTtd');
        const hidden = document.getElementById('ttdSiswa');
        const btnClear = document.getElementById('btnClear');

        if (canvas) {
            const ctx = canvas.getContext('2d');
            let drawing = false;
            let inked = false;
            let lastX = 0;
            let lastY = 0;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                return {
                    x: (e.clientX - rect.left) * (canvas.width / rect.width),
                    y: (e.clientY - rect.top) * (canvas.height / rect.height),
                };
            }

            function startStroke(e) {
                e.preventDefault();
                drawing = true;
                inked = true;
                const p = getPos(e);
                lastX = p.x;
                lastY = p.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                if (canvas.setPointerCapture) canvas.setPointerCapture(e.pointerId);
            }

            function moveStroke(e) {
                if (!drawing) return;
                e.preventDefault();
                const p = getPos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x;
                lastY = p.y;
            }

            function endStroke(e) {
                if (!drawing) return;
                drawing = false;
                ctx.closePath();
                hidden.value = inked ? canvas.toDataURL('image/png') : '';
            }

            canvas.addEventListener('pointerdown', startStroke);
            canvas.addEventListener('pointermove', moveStroke);
            canvas.addEventListener('pointerup', endStroke);
            canvas.addEventListener('pointercancel', endStroke);
            canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

            btnClear.addEventListener('click', function () {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                inked = false;
                hidden.value = '';
            });

            document.getElementById('formTtd').addEventListener('submit', function (e) {
                if (!hidden.value) {
                    e.preventDefault();
                    alert('Mohon goreskan tanda tangan siswa terlebih dahulu.');
                }
            });
        }
    });
</script>
</body>
</html>
