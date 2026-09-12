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

    {{-- Tabel --}}
    <div class="table-card-custom mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Daftar Dispensasi</h5>
        </div>
        <div class="table-responsive w-full overflow-x-auto">
            <table class="table table-custom align-middle mb-0 min-w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">NO</th>
                        <th>SISWA</th>
                        <th>KELAS</th>
                        <th>JAM KE</th>
                        <th>ALASAN</th>
                        <th>STATUS</th>
                        <th class="text-end whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dataDispensasi as $dispen)
                        <tr>
                            <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $dispen->siswa->nama ?? '-' }}</div>
                                <div class="text-muted small">NISN: {{ $dispen->siswa->nisn ?: '-' }}</div>
                            </td>
                            <td>{{ $dispen->siswa?->kelas?->nama ?? '-' }}</td>
                            <td>
                                @if($dispen->isTipeMasuk())
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle rounded-3 px-2 py-1 whitespace-nowrap">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk JP-{{ $dispen->jam_masuk_jp }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-dark border rounded-3 px-2 py-1 whitespace-nowrap">{{ $dispen->jam_ke_label }}</span>
                                @endif
                                @if(!$dispen->isTipeMasuk() && (!empty($dispen->jam_kembali_jp) || $dispen->isTidakKembaliHariIni()))
                                    <div class="small text-muted mt-1 whitespace-nowrap">
                                        @if($dispen->isTidakKembaliHariIni())
                                            <i class="bi bi-skip-end me-1"></i>Tidak kembali hari ini
                                        @elseif($dispen->isKembali())
                                            <i class="bi bi-check2-circle text-success me-1"></i>Kembali JP-{{ $dispen->jam_kembali_jp }}
                                        @else
                                            <i class="bi bi-arrow-return-left me-1"></i>Rencana kembali JP-{{ $dispen->jam_kembali_jp }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td style="max-width: 260px;"><span class="text-wrap">{{ $dispen->alasan }}</span></td>
                            <td><span class="badge {{ $dispen->status_badge }} rounded-pill px-2 py-2 whitespace-nowrap">{{ $dispen->status_label }}</span></td>
                            <td class="text-end whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap flex-wrap">
                                <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                       class="btn btn-sm btn-outline-dark rounded-3"
                                       title="Lihat Surat Dispensasi & TTD Digital (tab baru)">
                                    <i class="bi bi-file-earmark-text"></i>Lihat Surat
                                </a>
                                @if($dispen->approval_token)
                                    @php
                                        $approvalLink = route('dispen.approval.show', $dispen->approval_token);
                                        $waText = 'Halo Waka Kesiswaan, mohon tandatangani surat dispensasi berikut: ' . $approvalLink;
                                        $qrSvg = \App\Support\QrCodeHelper::svg($approvalLink, 6);
                                    @endphp
                                    <a href="https://wa.me/?text={{ urlencode($waText) }}" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-success rounded-3" title="Kirim WA ke Waka Kesiswaan">
                                        <i class="bi bi-whatsapp"></i>WA ke Waka
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-3"
                                            data-bs-toggle="modal" data-bs-target="#qrDispen{{ $dispen->id }}" title="Tampilkan QR approval">
                                        <i class="bi bi-qr-code"></i>QR
                                    </button>
                                    <div class="modal fade" id="qrDispen{{ $dispen->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-sm modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow-lg">
                                                <div class="modal-header border-0 pb-0">
                                                    <h6 class="modal-title fw-bold text-dark">Link Approval Dispensasi</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-center p-4">
                                                    <div class="d-inline-block bg-white rounded-4 p-3 shadow-sm">{!! $qrSvg !!}</div>
                                                    <div class="small text-muted mt-3 break-word">{{ $approvalLink }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($dispen->isApproved())
                                    @if(!$dispen->has_ttd)
                                        <a href="{{ route('piket.dispensasi.ttd', $dispen->id) }}"
                                           class="btn btn-sm btn-warning rounded-3" title="Lengkapi Tanda Tangan Siswa (konfirmasi akhir)">
                                            <i class="bi bi-pencil"></i>TTD Siswa
                                        </a>
                                    @endif
                                    <a href="{{ route('piket.dispensasi.surat', $dispen->id) }}" target="_blank"
                                       class="btn btn-sm btn-outline-primary rounded-3" title="Cetak Surat Dispen Resmi">
                                        <i class="bi bi-printer"></i>Cetak Surat
                                    </a>
                                @endif
                                @if($dispen->isBisaDibatalkan())
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3"
                                            data-bs-toggle="modal" data-bs-target="#batalkanDispen{{ $dispen->id }}"
                                            title="Batalkan surat dispensasi (wajib TTD siswa)">
                                        <i class="bi bi-x-circle"></i>Batalkan
                                    </button>
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
                            ({{ $dispen->siswa?->kelas?->nama ?? '-' }})
                        </p>
                        <p class="text-muted small mb-3">
                            Batalkan surat dispensasi berstatus <strong>{{ $dispen->status_label }}</strong>?
                            Absensi "Dispen" pada jurnal mengajar akan ditarik kembali.
                        </p>
                        <p class="text-muted small mb-2">
                            Wajib dibubuhi <strong>tanda tangan siswa</strong> ({{ $dispen->siswa?->nama ?? 'siswa' }})
                            sebagai bukti persetujuan pembatalan:
                        </p>
                        <canvas id="ttdPembatalan{{ $dispen->id }}" width="520" height="160"
                                class="w-100" style="border: 1px solid #dfe5ef; border-radius: 12px; background: #fff; touch-action: none; cursor: crosshair;"></canvas>
                        <input type="hidden" name="ttd_pembatalan" id="ttdPembatalanInput{{ $dispen->id }}" value="">
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-3"
                                    data-clear-canvas="ttdPembatalan{{ $dispen->id }}">
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
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
        };

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
    }

    document.querySelectorAll('[data-clear-canvas]').forEach((button) => {
        button.addEventListener('click', function () {
            const canvasId = this.getAttribute('data-clear-canvas');
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const input = document.getElementById(canvasId.replace('ttdPembatalan', 'ttdPembatalanInput'));
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

    document.querySelectorAll('canvas[id^="ttdPembatalan"]').forEach((canvas) => {
        initSignatureCanvas(canvas.id, canvas.id.replace('ttdPembatalan', 'ttdPembatalanInput'));
    });
</script>

@endsection