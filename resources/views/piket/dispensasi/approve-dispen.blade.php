<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Persetujuan Dispen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%);
            color: #0f172a;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }

        .ttd-img {
            max-height: 84px;
            max-width: 100%;
            object-fit: contain;
        }

        #canvasTtdWaka {
            width: 100%;
            height: auto;
            display: block;
            background: #fff;
            touch-action: none;
            cursor: crosshair;
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $siswa     = $dispensasi?->siswa;
        $kelas     = $siswa?->kelas;
        $buktiAda  = $dispensasi && !empty($dispensasi->bukti_surat)
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($dispensasi->bukti_surat);
    @endphp

    <div class="container" style="max-width: 760px;">
        {{-- Header --}}
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center mb-2"
                 style="width: 56px; height: 56px; border-radius: 16px; background: #0f172a; color: #fff;">
                <i class="bi bi-shield-check fs-3"></i>
            </div>
            <h4 class="fw-bold mb-1" style="letter-spacing: -0.02em;">Persetujuan Surat Dispensasi Siswa</h4>
            <p class="text-muted mb-0 small">
                Sistem Dispensasi Digital — Waka Kesiswaan / Penyetuju · via Tautan Unik
            </p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ===== INFO: SUDAH DIPROSES (approve/tolak) ===== --}}
        @if(in_array($state, ['approved', 'rejected'], true))
            <div class="alert alert-info border-0 rounded-4 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div><strong>Pengajuan ini sudah diproses.</strong> Tautan ini tidak dapat digunakan lagi untuk mengubah status surat dispensasi.</div>
            </div>
        @endif

        {{-- ===== STATE: Tautan tidak valid ===== --}}
        @if($state === 'invalid')
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-link-45deg" style="font-size: 3rem; color: #64748b;"></i>
                    <h5 class="fw-bold mt-3">Tautan Tidak Valid</h5>
                    <p class="text-muted mb-0">
                        Tautan persetujuan dispen tidak ditemukan atau telah kedaluwarsa.<br>
                        Silakan minta ulang tautan baru dari Guru Piket.
                    </p>
                </div>
            </div>
        @endif

        @if($dispensasi)
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="text-muted small mb-1" style="text-transform: uppercase; letter-spacing: 0.06em;">Nomor Surat</div>
                            <span class="fw-bold fs-5">{{ $dispensasi->nomor_surat }}</span>
                        </div>
                        <span class="badge {{ $dispensasi->status_badge }} rounded-pill px-3 py-2">{{ $dispensasi->status_label }}</span>
                    </div>

                    <hr>

                    {{-- Detail pengajuan --}}
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted small">Nama Siswa</div>
                            <div class="fw-semibold text-dark">{{ $siswa->nama ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">NISN / NIS</div>
                            <div class="fw-semibold text-dark">{{ $siswa->nisn ?? '-' }} / {{ $siswa->nis ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Kelas</div>
                            <div class="fw-semibold text-dark">{{ $kelas->nama ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Hari / Tanggal</div>
                            <div class="fw-semibold text-dark">{{ $dispensasi->tanggal->translatedFormat('l, d F Y') }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Jam KBM yang Ditinggalkan</div>
                            <div class="fw-semibold text-dark">{{ $dispensasi->jam_ke_label }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Alasan Kegiatan</div>
                            <div class="fw-semibold text-dark">{{ $dispensasi->alasan }}</div>
                        </div>
                    </div>

                    {{-- TTD Siswa --}}
                    <div class="mt-4 pt-3 border-top">
                        <div class="text-muted small mb-2"><i class="bi bi-pencil me-1"></i>Tanda Tangan Canvas Siswa / Pemohon</div>
                        @if($dispensasi->has_ttd)
                            <div class="border rounded-3 p-2 bg-light-subtle d-inline-block">
                                <img src="{{ $dispensasi->ttd_url }}" class="ttd-img" alt="TTD Siswa">
                            </div>
                        @else
                            <span class="text-muted small fst-italic">Belum ada tanda tangan siswa.</span>
                        @endif
                    </div>

                    {{-- Foto Bukti --}}
                    @if($dispensasi->bukti_surat)
                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small mb-2"><i class="bi bi-camera me-1"></i>Foto / Bukti Surat</div>
                            @if($buktiAda && $dispensasi->bukti_type === 'image')
                                <div class="d-inline-block border rounded-3 p-2 bg-light-subtle">
                                    <img src="{{ asset('storage/' . $dispensasi->bukti_surat) }}" class="ttd-img" alt="Foto Bukti Surat" style="max-height: 160px;">
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Foto bukti tidak tersedia.</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            @endif
        @php $showForm = $state === 'form'; @endphp

        {{-- ===== STATE: FORM PERSETUJUAN ===== --}}
        @if($showForm)
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-signature me-1"></i>Tanda Tangan Waka Kesiswaan / Penyetuju</h6>
                    <p class="text-muted small mb-3">
                        Goreskan tanda tangan pada area di bawah (mouse / layar sentuh), lalu pilih
                        <strong>Setujui &amp; Tanda Tangan</strong> untuk menyetujui dispen, atau <strong>Tolak</strong> beserta catatannya.
                    </p>

                    <form method="POST" action="{{ route('approval.submit', $token) }}" id="formApproval">
                        @csrf
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <canvas id="canvasTtdWaka" width="600" height="220">Browser Anda tidak mendukung Canvas.</canvas>
                        </div>
                        <div class="d-flex justify-content-end mb-3">
                            <button type="button" id="btnClearTtdWaka" class="btn btn-sm btn-outline-danger rounded-3">
                                <i class="bi bi-eraser me-1"></i> Hapus / Clear
                            </button>
                        </div>
                        <input type="hidden" name="ttd_waka" id="ttdWaka" value="">

                        {{-- Catatan penolakan (opsional) --}}
                        <div class="mb-3">
                            <label for="catatan_penolakan" class="form-label small fw-semibold text-secondary">
                                Catatan Penolakan <span class="text-muted fw-normal">(opsional, dipakai bila menolak)</span>
                            </label>
                            <textarea name="catatan_penolakan" id="catatan_penolakan" rows="2" maxlength="500"
                                      class="form-control rounded-3" placeholder="Misal: Surat bukti kurang lengkap..."></textarea>
                        </div>

                        <hr>

                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button type="submit" name="keputusan" value="tolak"
                                    class="btn btn-outline-danger rounded-3 px-4 py-2 fw-semibold">
                                <i class="bi bi-x-circle me-1"></i> Tolak
                            </button>
                            <button type="submit" name="keputusan" value="setujui" id="btnSetujui"
                                    class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Setujui &amp; Tanda Tangan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ===== STATE: SUDAH DISETUJUI ===== --}}
        @if($state === 'approved')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-success">
                <div class="card-body p-4 text-center">
                    <span class="d-inline-flex align-items-center justify-content-center mb-3"
                          style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #166534;">
                        <i class="bi bi-check2-circle fs-2"></i>
                    </span>
                    <h5 class="fw-bold text-success mb-1">Dispen Telah Disetujui</h5>
                    <p class="text-muted mb-0">
                        Surat dispensasi atas nama <strong>{{ $siswa?->nama ?? '-' }}</strong>
                        sudah disetujui dan ditandatangani Waka Kesiswaan / Penyetuju
                        pada {{ $dispensasi->approved_at?->translatedFormat('d F Y, H:i') }}.
                    </p>
                    @if($dispensasi->has_ttd_waka)
                        <div class="mt-4">
                            <div class="text-muted small mb-2">Tanda Tangan Waka Kesiswaan / Penyetuju</div>
                            <div class="border rounded-3 p-2 bg-white d-inline-block shadow-sm">
                                <img src="{{ $dispensasi->ttd_waka_url }}" class="ttd-img" alt="TTD Waka Kesiswaan">
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== STATE: DITOLAK ===== --}}
        @if($state === 'rejected')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-danger">
                <div class="card-body p-4 text-center">
                    <span class="d-inline-flex align-items-center justify-content-center mb-3"
                          style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #991b1b;">
                        <i class="bi bi-x-octagon fs-2"></i>
                    </span>
                    <h5 class="fw-bold text-danger mb-1">Dispen Ditolak</h5>
                    <p class="text-muted mb-0">
                        Surat dispensasi atas nama <strong>{{ $siswa?->nama ?? '-' }}</strong> ditolak
                        pada {{ $dispensasi->approved_at?->translatedFormat('d F Y, H:i') }}.
                    </p>
                    @if($dispensasi->catatan_penolakan)
                        <div class="alert alert-warning rounded-3 mt-3 mb-0 text-start">
                            <i class="bi bi-chat-left-text me-1"></i>
                            <strong>Catatan penolakan :</strong> {{ $dispensasi->catatan_penolakan }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="text-center text-muted small mt-4">
            <i class="bi bi-lock-fill me-1"></i> Halaman publik aman berbekal tautan unik.
            Jangan bagikan tautan ini kepada pihak yang tidak berwenang.
        </div>
    </div>

    {{-- Canvas TTD Waka --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvasTtdWaka = document.getElementById('canvasTtdWaka');
            const ttdWakaHidden = document.getElementById('ttdWaka');
            const btnClearTtdWaka = document.getElementById('btnClearTtdWaka');
            const formApproval = document.getElementById('formApproval');

            if (canvasTtdWaka) {
                const ctx = canvasTtdWaka.getContext('2d');
                let drawing = false;
                let inked = false;
                let lastX = 0;
                let lastY = 0;

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvasTtdWaka.width, canvasTtdWaka.height);
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                function getPos(e) {
                    const rect = canvasTtdWaka.getBoundingClientRect();
                    return {
                        x: (e.clientX - rect.left) * (canvasTtdWaka.width / rect.width),
                        y: (e.clientY - rect.top) * (canvasTtdWaka.height / rect.height),
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
                    if (canvasTtdWaka.setPointerCapture) {
                        canvasTtdWaka.setPointerCapture(e.pointerId);
                    }
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
                    ttdWakaHidden.value = inked ? canvasTtdWaka.toDataURL('image/png') : '';
                }

                canvasTtdWaka.addEventListener('pointerdown', startStroke);
                canvasTtdWaka.addEventListener('pointermove', moveStroke);
                canvasTtdWaka.addEventListener('pointerup', endStroke);
                canvasTtdWaka.addEventListener('pointercancel', endStroke);
                canvasTtdWaka.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

                btnClearTtdWaka.addEventListener('click', function () {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvasTtdWaka.width, canvasTtdWaka.height);
                    inked = false;
                    ttdWakaHidden.value = '';
                });

                if (formApproval) {
                    formApproval.addEventListener('submit', function (e) {
                        const clicked = document.activeElement;
                        if (clicked && clicked.name === 'keputusan' && clicked.value === 'setujui' && !ttdWakaHidden.value) {
                            e.preventDefault();
                            alert('Silakan goreskan tanda tangan Waka Kesiswaan / Penyetuju terlebih dahulu.');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>