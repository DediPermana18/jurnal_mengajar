@extends('layouts.app')

@section('title', 'Dispensasi Siswa - Guru Piket')

@section('content')
<div class="container-fluid px-0">

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                Dispensasi Siswa
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Kelola surat dispensasi siswa yang diterbitkan oleh Guru Piket.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="{{ route('piket.dispensasi.index') }}" class="d-flex align-items-center gap-2">
                <label class="text-muted fw-semibold small mb-0"><i class="bi bi-calendar3 me-1"></i>Tanggal:</label>
                <input type="date"
                       name="tanggal"
                       value="{{ $tanggal }}"
                       max="{{ $today }}"
                       class="form-control form-control-sm rounded-3"
                       style="width: auto;"
                       >
            </form>
            <a href="{{ route('piket.dispensasi.create') }}" class="btn btn-primary rounded-3 px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Buat Dispen
            </a>
        </div>
    </div>

    {{-- Alert --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('cancel'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-x-octagon-fill text-danger fs-5"></i>
            <div>{{ session('cancel') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <div>{{ $errors->first() }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info Bar --}}
    <div class="alert border-0 rounded-4 mb-4 py-3 px-4 d-flex align-items-center gap-3 {{ $tanggal === $today ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}" role="alert">
        <i class="bi {{ $tanggal === $today ? 'bi-calendar2-week-fill' : 'bi-calendar2-x-fill' }} fs-5"></i>
        <div>
            <strong>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</strong><br>
            <span class="small">Total <strong>{{ $totalHariIni }}</strong> surat dispensasi pada tanggal ini.</span>
        </div>
    </div>

    {{-- Tabel Utama Disatukan: Individu + Rombongan (Kolektif) --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Daftar Dispensasi</h5>
            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle rounded-pill px-3 py-2">
                {{ $dataGabungan->count() }} pengajuan
            </span>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">NO</th>
                        <th>SISWA / ROMBONGAN</th>
                        <th>TIPE</th>
                        <th>JAM KE</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th class="text-end whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataGabungan as $row)
                        @php
                            $tipe = $row['tipe'];
                            $isKolektif = $tipe === 'kolektif';
                            $dispen = $isKolektif ? null : $row['dispen'];
                            $kolektif = $isKolektif ? $row['kolektif'] : null;
                            $jamModel = $isKolektif ? $kolektif : $dispen;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td>
                                @if($isKolektif)
                                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle rounded-pill px-2 py-1 whitespace-nowrap">
                                        {{ $kolektif->siswaItems->count() }} Siswa
                                    </span>
                                    <div class="small text-muted mt-1" style="max-width: 320px;">
                                        {{ $kolektif->siswaItems->take(3)->map(fn ($it) => $it->siswa->nama ?? '?')->implode(', ') }}{{ $kolektif->siswaItems->count() > 3 ? ' …' : '' }}
                                    </div>
                                @else
                                    <div class="fw-semibold text-dark">{{ $dispen->siswa->nama ?? '-' }}</div>
                                    <div class="text-muted small">
                                        {{ $dispen->siswa?->kelas?->nama_lengkap ?? $dispen->siswa?->kelas?->nama_kelas ?? '-' }}
                                        <span class="mx-1">•</span>
                                        NISN: {{ $dispen->siswa?->nisn ?: '-' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($isKolektif)
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-1 whitespace-nowrap">
                                        <i class="bi bi-people me-1"></i>Kolektif
                                    </span>
                                @else
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1 whitespace-nowrap">Individu</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    if ($jamModel->isTipeMasuk()) {
                                        $jpMulaiText = 'Mulai: JP ' . $jamModel->jam_masuk_jp;
                                    } elseif ($jamModel->jam_keluar_jp) {
                                        $jpMulaiText = 'Mulai: JP ' . $jamModel->jam_keluar_jp;
                                    } else {
                                        $jpMulaiText = 'Mulai: ' . str_replace('Jam ', 'JP ', $jamModel->jam_ke_label);
                                    }
                                @endphp
                                <div class="fw-semibold text-dark whitespace-nowrap">{{ $jpMulaiText }}</div>
                                @if(!$jamModel->isTipeMasuk())
                                    @if($jamModel->isTidakKembaliHariIni())
                                        <div class="small text-muted mt-1 whitespace-nowrap">Tidak kembali hari ini</div>
                                    @elseif(!empty($jamModel->jam_kembali_jp))
                                        <div class="small text-muted mt-1 whitespace-nowrap">Kembali: JP {{ $jamModel->jam_kembali_jp }}</div>
                                    @endif
                                @endif
                            </td>
                            <td style="max-width: 260px;"><span class="text-wrap">{{ $jamModel->alasan }}</span></td>
                            <td><span class="badge {{ $jamModel->status_badge }} rounded-pill px-2 py-2 whitespace-nowrap">{{ $jamModel->status_label }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                <div class="d-flex justify-content-end align-items-center gap-1 flex-wrap">
                                    <a href="{{ $isKolektif ? route('piket.dispensasi.kolektif.surat', $kolektif->id) : route('piket.dispensasi.surat', $dispen->id) }}"
                                       target="_blank" class="btn btn-sm btn-outline-dark rounded-3" title="Lihat Surat Dispensasi (tab baru)">
                                        <i class="bi bi-file-earmark-text"></i>Surat / Detail
                                    </a>
                                    @php
                                        $aksiId = $isKolektif ? $kolektif->id : $dispen->id;
                                        $aksiPrefix = $isKolektif ? 'kolektif' : 'dispen';
                                        $aksiToken = $isKolektif ? $kolektif->approval_token : $dispen->approval_token;
                                        $aksiNomorSurat = $isKolektif ? $kolektif->nomor_surat : $dispen->nomor_surat;
                                        $aksiApprovalLink = $aksiToken ? route('dispen.approval.show', $aksiToken) : null;
                                        $aksiQrSvg = $aksiApprovalLink ? \App\Support\QrCodeHelper::svg($aksiApprovalLink, 6) : null;
                                        $aksiWaText = $aksiApprovalLink ? 'Halo Waka Kesiswaan, mohon tandatangani surat dispensasi berikut: '.$aksiApprovalLink : null;
                                        $showDrowpdown = (bool) $aksiToken
                                            || (!$isKolektif && (($dispen->isApproved() && !$dispen->has_ttd) || $dispen->isBisaDibatalkan()));
                                    @endphp
                                    @if($showDrowpdown)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary rounded-3" type="button"
                                                    data-bs-toggle="dropdown" aria-expanded="false" title="Aksi lainnya">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                                                @if($aksiApprovalLink)
                                                    <li>
                                                        <a class="dropdown-item" href="https://wa.me/?text={{ urlencode($aksiWaText) }}" target="_blank" rel="noopener">
                                                            <i class="bi bi-whatsapp me-2 text-success"></i>WA ke Waka
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#qr{{ ucfirst($aksiPrefix) }}{{ $aksiId }}">
                                                            <i class="bi bi-qr-code me-2"></i>QR Approval
                                                        </button>
                                                    </li>
                                                @endif
                                                @unless($isKolektif)
                                                    @if($dispen->isApproved() && !$dispen->has_ttd)
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('piket.dispensasi.ttd', $dispen->id) }}">
                                                                <i class="bi bi-pencil me-2 text-warning"></i>TTD Siswa
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if($dispen->isBisaDibatalkan())
                                                        <li>
                                                            <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#batalkanDispen{{ $dispen->id }}">
                                                                <i class="bi bi-x-circle me-2"></i>Batalkan
                                                            </button>
                                                        </li>
                                                    @endif
                                                @endunless
                                            </ul>
                                        </div>
                                    @endif
                                    @if($aksiApprovalLink)
                                        <div class="modal fade" id="qr{{ ucfirst($aksiPrefix) }}{{ $aksiId }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                                <div class="modal-content rounded-4 border-0 shadow-lg">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h6 class="modal-title fw-bold text-dark">Link Approval Dispensasi</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body text-center p-4">
                                                        <div class="d-flex justify-content-center mb-3">
                                                            <div class="d-inline-block bg-white rounded-4 p-3 shadow-sm" style="line-height: 0;">
                                                                {!! $aksiQrSvg !!}
                                                                <div class="mt-3 small fw-semibold text-muted" style="line-height: 1.2;">{{ $aksiNomorSurat }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control text-break" readonly value="{{ $aksiApprovalLink }}" aria-label="Link approval dispensasi">
                                                            <button type="button" class="btn btn-outline-secondary rounded-end-3" data-copy-url="{{ $aksiApprovalLink }}" title="Salin link approval">
                                                                <i class="bi bi-clipboard"></i> Salin
                                                            </button>
                                                        </div>
                                                        <div class="small text-muted mt-2">Pindai QR atau salin link untuk meminta tanda tangan Waka Kesiswaan.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                Belum ada surat dispensasi pada tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Pembatalan Dispensasi (wajib TTD siswa) --}}
    @foreach($dataDispensasi as $dispen)
        @if($dispen->isBisaDibatalkan())
        <div class="modal fade" id="batalkanDispen{{ $dispen->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('piket.dispensasi.batalkan', $dispen->id) }}" class="modal-content rounded-4 border-0 shadow-lg">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title fw-bold text-dark">
                            <i class="bi bi-x-circle text-danger me-1"></i> Batalkan Dispensasi
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-1">
                            Surat: <strong>{{ $dispen->nomor_surat }}</strong> —
                            <strong>{{ $dispen->siswa?->nama ?? '-' }}</strong>
                            ({{ $dispen->siswa?->kelas?->nama_lengkap ?? $dispen->siswa?->kelas?->nama_kelas ?? '-' }})
                        </p>
                        <p class="text-muted small mb-3">
                            Batalkan surat dispensasi berstatus <strong>{{ $dispen->status_label }}</strong>?
                            Absensi "Dispen" pada jurnal mengajar akan ditarik kembali.
                        </p>
                        <p class="text-muted small mb-2">
                            Wajib dibubuhi <strong>tanda tangan siswa</strong> ({{ $dispen->siswa?->nama ?? 'siswa' }})
                            sebagai bukti persetujuan pembatalan:
                        </p>
                        <canvas id="canvas-batal-{{ $dispen->id }}" height="150"
                                class="border rounded w-100" style="background: #fff; touch-action: none; cursor: crosshair;"></canvas>
                        <input type="hidden" name="ttd_pembatalan" id="ttd-batal-input-{{ $dispen->id }}" value="">
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3"
                                    data-clear-canvas="canvas-batal-{{ $dispen->id }}">
                                <i class="bi bi-eraser"></i> Ulang TTD
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Kembali</button>
                        <button type="submit" class="btn btn-danger rounded-3 px-4" data-submit-batalkan>
                            <i class="bi bi-check-lg me-1"></i> Batalkan Dispen
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @endforeach

</div>

<script>
    function initSignatureCanvas(canvasId, inputId) {
        const canvas = document.getElementById(canvasId);
        const input = document.getElementById(inputId);
        if (!canvas || !input) return;

        const ctx = canvas.getContext('2d');
        let drawing = false;
        let lastX = 0;
        let lastY = 0;

        const resizeCanvas = () => {
            const rect = canvas.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) return;

            const ratio = window.devicePixelRatio || 1;
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
        };

        const modalEl = canvas.closest('.modal');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function () {
                resizeCanvas();
            });
            if (window.jQuery) {
                window.jQuery(modalEl).on('shown.bs.modal', function () {
                    resizeCanvas();
                });
            }
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        const getPos = (event) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;
            return { x: clientX - rect.left, y: clientY - rect.top };
        };

        const startDrawing = (event) => {
            drawing = true;
            const pos = getPos(event);
            lastX = pos.x;
            lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        };

        const draw = (event) => {
            if (!drawing) return;
            const pos = getPos(event);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
        };

        const stopDrawing = () => {
            if (!drawing) return;
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        };

        canvas.addEventListener('pointerdown', startDrawing);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stopDrawing);
        canvas.addEventListener('pointerleave', stopDrawing);
        canvas.addEventListener('pointercancel', stopDrawing);

        canvas.addEventListener('touchstart', (event) => {
            event.preventDefault();
            startDrawing(event);
        }, { passive: false });
        canvas.addEventListener('touchmove', (event) => {
            event.preventDefault();
            draw(event);
        }, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchcancel', stopDrawing);

        canvas.clearCanvas = function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            input.value = '';
        };
    }

    document.querySelectorAll('[data-clear-canvas]').forEach((button) => {
        button.addEventListener('click', function () {
            const canvasId = this.getAttribute('data-clear-canvas');
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            if (typeof canvas.clearCanvas === 'function') {
                canvas.clearCanvas();
            } else {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }

            const inputId = canvasId.startsWith('canvas-batal-')
                ? canvasId.replace('canvas-batal-', 'ttd-batal-input-')
                : canvasId.replace('ttdPembatalan', 'ttdPembatalanInput');
            const input = document.getElementById(inputId);
            if (input) input.value = '';
        });
    });

    document.querySelectorAll('[data-submit-batalkan]').forEach((button) => {
        button.addEventListener('click', function (event) {
            const form = this.closest('form');
            if (!form) return;
            const hiddenInput = form.querySelector('input[name="ttd_pembatalan"]');
            if (!hiddenInput || !hiddenInput.value) {
                event.preventDefault();
                alert('Silakan siswa menandatangani canvas pembatalan terlebih dahulu.');
            }
        });
    });

    document.querySelectorAll('canvas[id^="canvas-batal-"], canvas[id^="ttdPembatalan"]').forEach((canvas) => {
        const inputId = canvas.id.startsWith('canvas-batal-')
            ? canvas.id.replace('canvas-batal-', 'ttd-batal-input-')
            : canvas.id.replace('ttdPembatalan', 'ttdPembatalanInput');
        initSignatureCanvas(canvas.id, inputId);
    });

    // Salin Link Approval (tombol di modal QR)
    function fallbackCopyUrl(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (e) {
        }
        document.body.removeChild(ta);
    }

    document.querySelectorAll('[data-copy-url]').forEach((button) => {
        button.addEventListener('click', function () {
            const url = this.getAttribute('data-copy-url');
            const original = this.innerHTML;
            const flashCopied = () => {
                this.innerHTML = '<i class="bi bi-check-lg me-1"></i>Tersalin';
                setTimeout(() => {
                    this.innerHTML = original;
                }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(flashCopied)
                    .catch(() => {
                        fallbackCopyUrl(url);
                        flashCopied();
                    });
            } else {
                fallbackCopyUrl(url);
                flashCopied();
            }
        });
    });
</script>

@endsection