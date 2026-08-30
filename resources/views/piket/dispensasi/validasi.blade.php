@php
    $valid    = $dispensasi->isApproved();
    $ditolak  = $dispensasi->status === \App\Models\DispensasiSiswa::STATUS_DITOLAK;
    $pending  = $dispensasi->status === \App\Models\DispensasiSiswa::STATUS_PENDING;
    $penandatangan = $dispensasi->approver ?? $dispensasi->guruPiket;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Surat Dispen — {{ config('app.name', 'Sekolah') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            max-width: 520px;
            width: 100%;
            padding: 36px 32px;
            text-align: center;
        }
        .badge-result {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .badge-result svg { width: 44px; height: 44px; }
        .result-valid { background: #dcfce7; color: #166534; }
        .result-pending { background: #fef3c7; color: #92400e; }
        .result-rejected { background: #fee2e2; color: #991b1b; }
        h1 { font-size: 22px; letter-spacing: -0.02em; margin-bottom: 6px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .info { text-align: left; border-top: 1px dashed #e2e8f0; border-bottom: 1px dashed #e2e8f0; padding: 12px 0; margin-bottom: 20px; }
        .info .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .info .row .label { color: #64748b; }
        .info .row .value { color: #0f172a; font-weight: 600; text-align: right; max-width: 60%; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
        }
        .btn-print { background: #0f172a; color: #fff; }
        .btn-print:hover { background: #1e293b; }
        .footer { margin-top: 18px; color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if($valid)
            <div class="badge-result result-valid">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </div>
            <h1>SURAT VALID</h1>
            <p class="subtitle">Surat dispensasi ini <strong>terverifikasi dan disetujui</strong>.</p>
        @elseif($pending)
            <div class="badge-result result-pending">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
            </div>
            <h1>MENUNGGU PERSETUJUAN</h1>
            <p class="subtitle">Pengajuan dispensasi ini masih dalam status <strong>Pending</strong>.</p>
        @elseif($ditolak)
            <div class="badge-result result-rejected">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </div>
            <h1>SURAT DITOLAK</h1>
            <p class="subtitle">Pengajuan dispensasi ini <strong>ditolak</strong> oleh Guru Piket.</p>
        @endif

        @if($dispensasi->has_ttd)
            <div class="ttd" style="margin: 0 auto 20px; max-width: 200px; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 12px; background: #f8fafc;">
                <div style="font-size: 11px; color: #64748b; margin-bottom: 6px;">TTD Siswa</div>
                <img src="{{ $dispensasi->ttd_url }}" alt="TTD Siswa"
                     style="max-height: 70px; max-width: 100%; object-fit: contain;">
            </div>
        @endif

        <div class="info">
            <div class="row"><span class="label">Nomor</span><span class="value">{{ $dispensasi->nomor_surat }}</span></div>
            <div class="row"><span class="label">Nama Siswa</span><span class="value">{{ $dispensasi->siswa->nama ?? '-' }}</span></div>
            <div class="row"><span class="label">NISN / NIS</span><span class="value">{{ $dispensasi->siswa->nisn ?? '-' }} / {{ $dispensasi->siswa->nis ?? '-' }}</span></div>
            <div class="row"><span class="label">Kelas</span><span class="value">{{ $dispensasi->siswa?->kelas?->nama ?? '-' }}</span></div>
            <div class="row"><span class="label">Tanggal</span><span class="value">{{ $dispensasi->tanggal->translatedFormat('d F Y') }}</span></div>
            <div class="row"><span class="label">Jam Ke</span><span class="value">{{ $dispensasi->jam_ke_label }}</span></div>
            <div class="row"><span class="label">Alasan</span><span class="value">{{ $dispensasi->alasan }}</span></div>
            @if($valid)
                <div class="row"><span class="label">Disetujui oleh</span><span class="value">{{ $penandatangan->nama ?? '-' }} ({{ $penandatangan->nip ?? '-' }})</span></div>
                <div class="row"><span class="label">Waktu</span><span class="value">{{ $dispensasi->approved_at?->translatedFormat('d M Y, H:i') }}</span></div>
            @elseif($ditolak && $dispensasi->catatan_penolakan)
                <div class="row"><span class="label">Catatan tolak</span><span class="value">{{ $dispensasi->catatan_penolakan }}</span></div>
            @endif
        </div>

        <button type="button" class="btn btn-print" onclick="window.print()">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
            </svg>
            Cetak Halaman Validasi
        </button>

        <div class="footer">
            {{ strtoupper(config('app.name', 'WebJournal Management System')) }} &bull; SISTEM DISPENSASI DIGITAL
        </div>
    </div>
</body>
</html>