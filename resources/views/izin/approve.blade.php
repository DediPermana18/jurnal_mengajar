<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Persetujuan Izin Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%); color: #0f172a; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .ttd-img { max-height: 84px; max-width: 100%; object-fit: contain; }
        #canvasTtd { width: 100%; height: auto; display: block; background: #fff; touch-action: none; cursor: crosshair; border: 1px dashed #cbd5e1; border-radius: 0.5rem; }
    </style>
</head>
<body class="py-4 py-md-5">
    @php
        $guru = $izin?->user;
    @endphp

    <div class="container" style="max-width: 760px;">
        {{-- Header --}}
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px; border-radius: 16px; background: #0f172a; color: #fff;">
                <i class="bi bi-person-check fs-3"></i>
            </div>
            <h4 class="fw-bold mb-1" style="letter-spacing: -0.02em;">Persetujuan Izin Guru</h4>
            <p class="text-muted mb-0 small">Sistem Izin Digital — Waka / Kepala Sekolah · via Tautan Unik</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert"><i class="bi bi-check-circle-fill fs-5"></i><div>{{ session('success') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center gap-2" role="alert"><i class="bi bi-exclamation-triangle-fill fs-5"></i><div>{{ session('error') }}</div><button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>
        @endif

        {{-- ===== INFO: SUDAH DIPROSES (approve/tolak) ===== --}}
        @if(in_array($state, ['approved', 'rejected'], true))
            <div class="alert alert-info border-0 rounded-4 shadow-sm d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div><strong>Pengajuan ini sudah diproses.</strong> Tautan ini tidak dapat digunakan lagi untuk mengubah status pengajuan izin.</div>
            </div>
        @endif

        {{-- ===== INVALID ===== --}}
        @if($state === 'invalid')
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-link-45deg" style="font-size: 3rem; color: #64748b;"></i>
                    <h5 class="fw-bold mt-3">Tautan Tidak Valid</h5>
                    <p class="text-muted mb-0">Tautan persetujuan izin tidak ditemukan atau telah kedaluwarsa. Silakan minta ulang dari Guru Piket.</p>
                </div>
            </div>
        @endif

        @if($izin)
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div class="fw-bold fs-5">Pengajuan Izin Guru</div>
                        <span class="badge {{ $izin->status_badge }} rounded-pill px-3 py-2">{{ $izin->status_label }}</span>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-muted small">Nama Guru</div><div class="fw-semibold text-dark">{{ $guru->nama ?? '-' }}</div></div>
                        <div class="col-6"><div class="text-muted small">NIP</div><div class="fw-semibold text-dark">{{ $guru->nip ?? '-' }}</div></div>
                        <div class="col-6"><div class="text-muted small">Hari / Tanggal</div><div class="fw-semibold text-dark">{{ $izin->tanggal->translatedFormat('l, d F Y') }}</div></div>
                        <div class="col-12"><div class="text-muted small">Alasan</div><div class="fw-semibold text-dark">{{ $izin->alasan }}</div></div>
                        @if($izin->tugas_siswa)
                            <div class="col-12"><div class="text-muted small">Tugas untuk Siswa</div><div class="fw-semibold text-dark">{{ $izin->tugas_siswa }}</div></div>
                        @endif
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <div class="text-muted small mb-2"><i class="bi bi-pencil me-1"></i>Tanda Tangan Guru (Pemohon)</div>
                        @if($izin->has_ttd_guru)
                            <div class="border rounded-3 p-2 bg-light-subtle d-inline-block"><img src="{{ $izin->ttd_guru_url }}" class="ttd-img" alt="TTD Guru"></div>
                        @else
                            <span class="text-muted small fst-italic">Belum ada tanda tangan guru.</span>
                        @endif
                    </div>

                    @if($izin->has_ttd_waka)
                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small mb-2">Tanda Tangan Waka</div>
                            <div class="border rounded-3 p-2 bg-light-subtle d-inline-block"><img src="{{ $izin->ttd_waka_url }}" class="ttd-img" alt="TTD Waka"></div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== SOON (belum tiba langkah publik / belum giliran) ===== --}}
        @if($state === 'soon')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-warning">
                <div class="card-body p-4 text-center">
                    <i class="bi bi-hourglass-split fs-2 text-warning"></i>
                    <h5 class="fw-bold mt-2 mb-1">Masih Menunggu Langkah Sebelumnya</h5>
                    <p class="text-muted mb-0">Pengajuan izin ini belum sampai atau belum waktunya pada langkah yang ditujukan tautan ini. Silakan kembali lagi setelah langkah sebelumnya diselesaikan.</p>
                </div>
            </div>
        @endif

        {{-- ===== WAKA DONE (setelah Waka menandatangani, tanpa form) ===== --}}
        @if($state === 'waka_done')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-success mb-4">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <span class="d-inline-flex align-items-center justify-content-center mb-2" style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #166534;"><i class="bi bi-check2-circle fs-2"></i></span>
                        <h5 class="fw-bold text-success mb-1">Sukses Verifikasi Waka Kurikulum</h5>
                        <p class="text-muted mb-0">Pengajuan izin atas nama <strong>{{ $guru->nama ?? '-' }}</strong> pada {{ $izin->tanggal->translatedFormat('d F Y') }} telah diverifikasi dan ditandatangani Waka Kurikulum.<br>Langkah tanda tangan Waka selesai &mdash; tidak dapat diubah lagi.</p>
                    </div>

                    <hr>

                    <p class="text-muted small mb-3"><i class="bi bi-send me-1"></i>Tahap terakhir menunggu persetujuan Kepala Sekolah. Teruskan tautan unik (token khusus Kepala Sekolah) melalui WhatsApp:</p>

                    @if($kepsek_wa_url && $kepsek_link)
                        <div class="d-flex flex-column flex-md-row align-items-stretch gap-2">
                            <a href="{{ $kepsek_wa_url }}" target="_blank" rel="noopener" class="btn btn-success rounded-3 px-4 py-2 fw-semibold flex-fill">
                                <i class="bi bi-whatsapp me-1"></i> Teruskan ke Kepala Sekolah via WhatsApp
                            </a>
                            <button type="button" class="btn btn-outline-success rounded-3 px-4 py-2 fw-semibold"
                                    data-salin-link="{{ $kepsek_link }}" id="btnSalinLinkKepsek">
                                <i class="bi bi-clipboard me-1"></i> Salin Tautan Kepsek
                            </button>
                        </div>
                        {{-- Fallback manual tanpa JavaScript --}}
                        <noscript>
                            <div class="mt-3 p-3 border rounded-3 bg-light-subtle">
                                <div class="text-muted small mb-1">Salin tautan Kepala Sekolah:</div>
                                <code class="text-break">{{ $kepsek_link }}</code>
                            </div>
                        </noscript>
                    @else
                        <div class="alert alert-warning rounded-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Tautan Kepala Sekolah belum tersedia. Silakan hubungi Guru Piket / admin untuk membuat tautan tersebut.</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== FORM WAKA ===== --}}
        @if($state === 'waka')
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-signature me-1"></i>Tanda Tangan Waka Kurikulum</h6>
                    <p class="text-muted small mb-3">Goreskan tanda tangan Waka Kurikulum pada area di bawah, lalu pilih <strong>Setujui</strong> untuk melanjutkan ke Kepala Sekolah, atau <strong>Tolak</strong> beserta catatannya.</p>
                    <form method="POST" action="{{ route('izin.approval.submit', $token) }}" id="formApproval">
                        @csrf
                        <canvas id="canvasTtd" width="600" height="220">Browser tidak mendukung Canvas.</canvas>
                        <div class="d-flex justify-content-end my-2">
                            <button type="button" id="btnClear" class="btn btn-sm btn-outline-danger rounded-3"><i class="bi bi-eraser me-1"></i> Hapus</button>
                        </div>
                        <input type="hidden" name="ttd_waka" id="ttdWaka" value="">
                        <input type="hidden" name="ttd_kepsek" id="ttdKepsek" value="">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Catatan Penolakan <span class="text-muted fw-normal">(opsional, bila menolak)</span></label>
                            <textarea name="catatan_penolakan" rows="2" maxlength="500" class="form-control rounded-3" placeholder="Contoh: Bukti surat kurang lengkap..."></textarea>
                        </div>
                        <hr>
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button type="submit" name="keputusan" value="tolak" class="btn btn-outline-danger rounded-3 px-4 py-2 fw-semibold"><i class="bi bi-x-circle me-1"></i> Tolak</button>
                            <button type="submit" name="keputusan" value="setujui" id="btnSetujui" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-check2-circle me-1"></i> Setujui & Lanjutkan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ===== FORM KEPSEK ===== --}}
        @if($state === 'kepsek')
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-signature me-1"></i>Tanda Tangan & Persetujuan Kepala Sekolah</h6>
                    <p class="text-muted small mb-3">Ini adalah langkah <strong>terakhir</strong>. Goreskan tanda tangan pada area di bawah, lalu pilih <strong>Setujui</strong> untuk mengesahkan izin, atau <strong>Tolak</strong> beserta catatannya.</p>
                    <form method="POST" action="{{ route('izin.approval.submit', $token) }}" id="formApproval">
                        @csrf
                        <canvas id="canvasTtd" width="600" height="220">Browser tidak mendukung Canvas.</canvas>
                        <div class="d-flex justify-content-end my-2">
                            <button type="button" id="btnClear" class="btn btn-sm btn-outline-danger rounded-3"><i class="bi bi-eraser me-1"></i> Hapus</button>
                        </div>
                        <input type="hidden" name="ttd_waka" id="ttdWaka" value="">
                        <input type="hidden" name="ttd_kepsek" id="ttdKepsek" value="">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Catatan Penolakan <span class="text-muted fw-normal">(opsional, bila menolak)</span></label>
                            <textarea name="catatan_penolakan" rows="2" maxlength="500" class="form-control rounded-3"></textarea>
                        </div>
                        <hr>
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <button type="submit" name="keputusan" value="tolak" class="btn btn-outline-danger rounded-3 px-4 py-2 fw-semibold"><i class="bi bi-x-circle me-1"></i> Tolak</button>
                            <button type="submit" name="keputusan" value="setujui" id="btnSetujui" class="btn btn-success rounded-3 px-4 py-2 fw-semibold shadow-sm"><i class="bi bi-check2-circle me-1"></i> Setujui & Tanda Tangan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- ===== APPROVED ===== --}}
        @if($state === 'approved')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-success">
                <div class="card-body p-4 text-center">
                    <span class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #166534;"><i class="bi bi-check2-circle fs-2"></i></span>
                    <h5 class="fw-bold text-success mb-1">Izin Telah Disetujui</h5>
                    <p class="text-muted mb-0">Pengajuan izin atas nama <strong>{{ $guru->nama ?? '-' }}</strong> telah disetujui dan ditandatangani Kepala Sekolah pada {{ $izin->approved_at?->translatedFormat('d F Y, H:i') }}.</p>
                    @if($izin->has_ttd_kepsek)
                        <div class="mt-4"><div class="text-muted small mb-2">Tanda Tangan Kepala Sekolah</div><div class="border rounded-3 p-2 bg-white d-inline-block shadow-sm"><img src="{{ $izin->ttd_kepsek_url }}" class="ttd-img" alt="TTD Kepala Sekolah"></div></div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== REJECTED ===== --}}
        @if($state === 'rejected')
            <div class="card border-0 shadow-sm rounded-4 border-start border-5 border-danger">
                <div class="card-body p-4 text-center">
                    <span class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #991b1b;"><i class="bi bi-x-octagon fs-2"></i></span>
                    <h5 class="fw-bold text-danger mb-1">Izin Ditolak</h5>
                    <p class="text-muted mb-0">Pengajuan izin atas nama <strong>{{ $guru->nama ?? '-' }}</strong> ditolak pada {{ $izin->approved_at?->translatedFormat('d F Y, H:i') }}.</p>
                    @if($izin->catatan_penolakan)
                        <div class="alert alert-warning rounded-3 mt-3 mb-0 text-start"><i class="bi bi-chat-left-text me-1"></i><strong>Catatan penolakan:</strong> {{ $izin->catatan_penolakan }}</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="text-center text-muted small mt-4"><i class="bi bi-lock-fill me-1"></i> Halaman publik aman berbekal tautan unik. Jangan bagikan kepada pihak yang tidak berwenang.</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('canvasTtd');
            const ttdWakaHidden = document.getElementById('ttdWaka');
            const ttdKepsekHidden = document.getElementById('ttdKepsek');
            const btnClear = document.getElementById('btnClear');
            const formApproval = document.getElementById('formApproval');
            const state = @json($state);

            if (canvas) {
                const ctx = canvas.getContext('2d');
                let drawing = false, inked = false, lastX = 0, lastY = 0;
                ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.strokeStyle = '#0f172a'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round';

                function getPos(e) { const r = canvas.getBoundingClientRect(); return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) }; }
                function start(e) { e.preventDefault(); drawing = true; inked = true; const p = getPos(e); lastX = p.x; lastY = p.y; ctx.beginPath(); ctx.moveTo(lastX, lastY); }
                function move(e) { if (!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); lastX = p.x; lastY = p.y; }
                function end(e) { if (!drawing) return; drawing = false; ctx.closePath(); if (inked) { const data = canvas.toDataURL('image/png'); if (state === 'waka') ttdWakaHidden.value = data; else ttdKepsekHidden.value = data; } }

                canvas.addEventListener('pointerdown', start);
                canvas.addEventListener('pointermove', move);
                canvas.addEventListener('pointerup', end);
                canvas.addEventListener('pointercancel', end);
                canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

                btnClear.addEventListener('click', function () { ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height); inked = false; ttdWakaHidden.value = ''; ttdKepsekHidden.value = ''; });

                formApproval.addEventListener('submit', function (e) {
                    const clicked = document.activeElement;
                    const requireTtd = (clicked && clicked.name === 'keputusan' && clicked.value === 'setujui');
                    if (requireTtd) {
                        const val = state === 'waka' ? ttdWakaHidden.value : ttdKepsekHidden.value;
                        if (!val) { e.preventDefault(); alert('Silakan goreskan tanda tangan terlebih dahulu.'); }
                    }
                });
            }

            // Salin tautan Kepala Sekolah dari halaman Sukses Verifikasi Waka.
            const btnSalinLinkKepsek = document.getElementById('btnSalinLinkKepsek');
            if (btnSalinLinkKepsek) {
                btnSalinLinkKepsek.addEventListener('click', function () {
                    const link = this.dataset.salinLink || '';
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(link).then(function () {
                            const old = btnSalinLinkKepsek.innerHTML;
                            btnSalinLinkKepsek.innerHTML = '<i class="bi bi-check-lg me-1"></i>Tersalin!';
                            setTimeout(function () { btnSalinLinkKepsek.innerHTML = old; }, 1600);
                        }, function () { window.prompt('Salin tautan Kepala Sekolah:', link); });
                    } else { window.prompt('Salin tautan Kepala Sekolah:', link); }
                });
            }
        });
    </script>
</body>
</html>
