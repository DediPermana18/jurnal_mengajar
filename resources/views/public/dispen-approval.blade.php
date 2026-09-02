<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Approval Dispensasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        .approval-card {
            max-width: 760px;
            margin: 32px auto;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
        }
        .signature-box {
            width: 100%;
            height: 200px;
            background: #fff;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            cursor: crosshair;
            touch-action: none;
        }
        .meta-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        @media (max-width: 576px) {
            .approval-card {
                margin: 18px 12px;
                border-radius: 18px;
            }
            .signature-box {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="approval-card p-3 p-md-4">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 54px; height: 54px; background: #0f172a; color: white;">
                    <i class="bi bi-clipboard-check fs-4"></i>
                </div>
                <h4 class="fw-bold mb-1">Persetujuan Dispensasi</h4>
                <p class="text-muted mb-0 small">Waka Kesiswaan • Tanda Tangan Digital</p>
            </div>

            @if(session('success'))
                <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info rounded-4 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>{{ session('info') }}</div>
                </div>
            @endif

            @if($invalid ?? false)
                <div class="card border-0 bg-light rounded-4 text-center py-5">
                    <i class="bi bi-link-45deg fs-1 text-muted"></i>
                    <h5 class="fw-bold mt-3 mb-2">Tautan Tidak Valid</h5>
                    <p class="text-muted mb-0 small">Token approval dispensasi tidak ditemukan atau sudah tidak berlaku.</p>
                </div>
            @elseif($alreadySigned ?? false)
                <div class="card border-0 bg-success-subtle rounded-4 text-center py-4">
                    <i class="bi bi-check2-circle fs-1 text-success"></i>
                    <h5 class="fw-bold mt-3 mb-2 text-success">Sudah Ditandatangani</h5>
                    <p class="text-muted mb-0 small">Surat dispensasi ini telah ditandatangani Waka Kesiswaan.</p>
                    @if($dispensasi && $dispensasi->ttd_waka)
                        <div class="mt-4 d-inline-block bg-white rounded-3 p-2 border">
                            <img src="{{ $dispensasi->ttd_waka }}" alt="TTD Waka" style="max-height: 90px; max-width: 100%; object-fit: contain;">
                        </div>
                    @endif
                </div>
            @else
                <div class="card border-0 bg-light rounded-4 p-3 p-md-4 mb-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="meta-label">Nama Siswa</div>
                            <div class="fw-bold text-dark">{{ $dispensasi->siswa?->nama ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Kelas</div>
                            <div class="fw-bold text-dark">{{ $dispensasi->siswa?->kelas?->nama_kelas ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Jam KBM</div>
                            <div class="fw-bold text-dark">{{ $dispensasi->jam_ke_label ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Tanggal</div>
                            <div class="fw-bold text-dark">{{ $dispensasi->tanggal?->translatedFormat('d F Y') ?? '-' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="meta-label">Alasan</div>
                            <div class="fw-bold text-dark">{{ $dispensasi->alasan ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('dispen.approval.store', $token) }}" id="approvalForm">
                    @csrf
                    <input type="hidden" name="ttd_waka" id="ttdWakaInput" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Tanda Tangan Waka Kesiswaan</label>
                        <canvas id="signatureCanvas" class="signature-box" width="600" height="200"></canvas>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <button type="button" id="resetSignature" class="btn btn-outline-secondary rounded-3 px-3 py-2">
                            <i class="bi bi-eraser me-1"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i>Simpan TTD
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const form = document.getElementById('approvalForm');
        const hiddenInput = document.getElementById('ttdWakaInput');
        const resetBtn = document.getElementById('resetSignature');

        if (canvas && hiddenInput) {
            const ctx = canvas.getContext('2d');
            let drawing = false;

            const resizeCanvas = () => {
                const ratio = window.devicePixelRatio || 1;
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.lineWidth = 2.5;
                ctx.strokeStyle = '#0f172a';
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            };

            const getPoint = (event) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            };

            const start = (event) => {
                drawing = true;
                const p = getPoint(event);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            };

            const move = (event) => {
                if (!drawing) return;
                const p = getPoint(event);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            };

            const stop = () => {
                drawing = false;
                hiddenInput.value = canvas.toDataURL('image/png');
            };

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            canvas.addEventListener('pointerdown', start);
            canvas.addEventListener('pointermove', move);
            canvas.addEventListener('pointerup', stop);
            canvas.addEventListener('pointerleave', stop);
            canvas.addEventListener('pointercancel', stop);

            canvas.addEventListener('touchstart', function (event) { event.preventDefault(); start(event); }, { passive: false });
            canvas.addEventListener('touchmove', function (event) { event.preventDefault(); move(event); }, { passive: false });
            canvas.addEventListener('touchend', stop);
            canvas.addEventListener('touchcancel', stop);

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hiddenInput.value = '';
                });
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!hiddenInput.value) {
                        event.preventDefault();
                        alert('Silakan tanda tangan terlebih dahulu sebelum menyimpan.');
                    }
                });
            }
        }
    </script>
</body>
</html>
