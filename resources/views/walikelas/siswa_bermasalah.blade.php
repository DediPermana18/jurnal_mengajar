@extends('layouts.app')

@section('title', 'Catatan Siswa Bermasalah - Wali Kelas')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-black text-dark mb-1" style="letter-spacing: -0.02em; font-weight: 800; font-size: 1.75rem;">
                ⚠️ Catatan Siswa Bermasalah
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">
                Rekap kedisiplinan siswa kelas Anda dari database — terlambat (Satpam), alpha, dan dispensasi.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 text-muted small">
            <i class="bi bi-people-fill"></i>
            <span>
                @foreach($kelasWali as $i => $kelas)
                    {{ $i > 0 ? '· ' : '' }}{{ $kelas->nama_lengkap }}
                @endforeach
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="stat-card-custom">
                <div class="stat-card-title">Total Siswa Kelas</div>
                <div class="stat-number-large text-primary">{{ count($rekap) }}</div>
                <div class="stat-card-label">terdaftar di kelas wali</div>
            </div>
        </div>
        @php
            $adaPermasalahan = collect($rekap)->filter(fn ($r) => $r['total_terlambat'] > 0 || $r['total_alpha'] > 0 || $r['total_dispen'] > 0)->count();
            $adaOrangTuaDipanggil = collect($rekap)->filter(fn ($r) => $r['tindak_lanjut'] && in_array($r['tindak_lanjut']->status, ['dipanggil', 'selesai']))->count();
        @endphp
        <div class="col-6 col-xl-3">
            <div class="stat-card-custom">
                <div class="stat-card-title">Siswa Perlu Perhatian</div>
                <div class="stat-number-large text-warning">{{ $adaPermasalahan }}</div>
                <div class="stat-card-label">punya riwayat terlambat / alpha / dispen</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card-custom">
                <div class="stat-card-title">Catatan Tindak Lanjut</div>
                <div class="stat-number-large text-info">{{ $statistikTindakLanjut->count() }}</div>
                <div class="stat-card-label">tersimpan di database</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card-custom">
                <div class="stat-card-title">Ortu Terpanggil</div>
                <div class="stat-number-large text-success">{{ $adaOrangTuaDipanggil }}</div>
                <div class="stat-card-label">status dipanggil / selesai</div>
            </div>
        </div>
    </div>

    <div class="table-card-custom">
        <div class="d-flex align-items-center gap-2 mb-3 text-muted small">
            <i class="bi bi-info-circle"></i>
            <span>Keterlambatan bersumber dari catatan Satpam yang diteruskan ke Wali Kelas; Alpha dari presensi Jurnal Guru Mapel.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kategori</th>
                        <th>Riwayat Terlambat (Satpam)</th>
                        <th>Tindak Lanjut Wali Kelas</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekap as $item)
                        @php $siswa = $item['siswa']; @endphp
                        <tr>
                            <td>
                                <strong class="d-block text-dark">{{ $siswa->nama }}</strong>
                                <small class="text-muted">NIS: {{ $siswa->nis ?? '-' }}</small>
                                <div><small class="text-muted">{{ $siswa->kelas?->nama_lengkap ?? '-' }}</small></div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1 align-items-start">
                                    @if($item['total_terlambat'] > 0)
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">
                                            <i class="bi bi-clock-history me-1"></i>{{ $item['total_terlambat'] > 3 ? 'Terlambat > 3x' : 'Terlambat ' . $item['total_terlambat'] . 'x' }}
                                        </span>
                                    @endif
                                    @if($item['total_alpha'] > 0)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">
                                            <i class="bi bi-x-octagon me-1"></i>{{ $item['total_alpha'] > 3 ? 'Alpha > 3x' : 'Alpha ' . $item['total_alpha'] . 'x' }}
                                        </span>
                                    @endif
                                    @if($item['total_dispen'] > 0)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                            <i class="bi bi-door-open me-1"></i>Dispen {{ $item['total_dispen'] }}x
                                        </span>
                                    @endif
                                    @if($item['total_terlambat'] === 0 && $item['total_alpha'] === 0 && $item['total_dispen'] === 0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Tidak ada catatan</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($item['riwayat_terlambat']->isNotEmpty())
                                    <div class="d-flex flex-column gap-1">
                                        @foreach($item['riwayat_terlambat']->take(3) as $t)
                                            <div class="small">
                                                <span class="badge bg-light text-secondary border border-light-subtle rounded-pill me-1">{{ $t->tanggal?->format('d M Y') }}</span>
                                                <span class="text-danger fw-semibold">{{ $t->jam_masuk?->format('H:i') }}</span>
                                                @if($t->keterangan)
                                                    <span class="text-muted">— {{ $t->keterangan }}</span>
                                                @endif
                                                <span class="text-muted">(catat: {{ $t->satpam?->nama ?? '-' }})</span>
                                            </div>
                                        @endforeach
                                        @if($item['riwayat_terlambat']->count() > 3)
                                            <small class="text-muted">+{{ $item['riwayat_terlambat']->count() - 3 }} catatan lainnya</small>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                @if($item['tindak_lanjut'])
                                    @php $tl = $item['tindak_lanjut']; @endphp
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge {{ in_array($tl->status, ['dipanggil', 'selesai']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} rounded-pill text-start">
                                            <i class="bi bi-telephone me-1"></i>
                                            {{ $tl->jenis_tindakan === 'panggil_ortu' ? 'Panggil Ortu' : 'Catatan Wali' }} — {{ ucfirst($tl->status) }}
                                        </span>
                                        @if($tl->catatan)
                                            <small class="text-muted">{{ $tl->catatan }}</small>
                                        @endif
                                        <small class="text-muted">Update: {{ $tl->updated_at?->format('d M Y H:i') }}</small>
                                    </div>
                                @else
                                    <span class="text-muted small">Belum ada tindak lanjut</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item['total_terlambat'] > 0 || $item['total_alpha'] > 0 || $item['total_dispen'] > 0)
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-2"
                                            data-bs-toggle="modal" data-bs-target="#tindakLanjutModal{{ $siswa->id }}">
                                        <i class="bi bi-telephone me-1"></i> Tindak Lanjut
                                    </button>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>

                        {{-- Modal Tindak Lanjut --}}
                        <div class="modal fade" id="tindakLanjutModal{{ $siswa->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content rounded-4">
                                    <form method="POST" action="{{ route('walikelas.siswa-bermasalah.store') }}">
                                        @csrf
                                        <input type="hidden" name="id_siswa" value="{{ $siswa->id }}">
                                        <div class="modal-header border-0">
                                            <div>
                                                <h5 class="modal-title fw-bold">Tindak Lanjut — {{ $siswa->nama }}</h5>
                                                <p class="text-muted small mb-0">{{ $siswa->kelas?->nama_lengkap }}</p>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body pt-0">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">Jenis Tindakan</label>
                                                <select name="jenis_tindakan" class="form-select">
                                                    <option value="panggil_ortu" @selected(optional($item['tindak_lanjut'])->jenis_tindakan === 'panggil_ortu')>Panggil Orang Tua / Wali</option>
                                                    <option value="catatan" @selected(optional($item['tindak_lanjut'])->jenis_tindakan === 'catatan')>Catatan Tindak Lanjut</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="belum" @selected(optional($item['tindak_lanjut'])->status === 'belum')>Belum</option>
                                                    <option value="dipanggil" @selected(optional($item['tindak_lanjut'])->status === 'dipanggil')>Dipanggil</option>
                                                    <option value="selesai" @selected(optional($item['tindak_lanjut'])->status === 'selesai')>Selesai</option>
                                                </select>
                                            </div>
                                            <div class="mb-1">
                                                <label class="form-label fw-semibold small text-uppercase text-secondary">Catatan</label>
                                                <textarea name="catatan" rows="3" class="form-control" maxlength="1000"
                                                          placeholder="Hasil konfirmasi / tindakan yang dilakukan">{{ optional($item['tindak_lanjut'])->catatan }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0">
                                            <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary rounded-3 fw-semibold">
                                                <i class="bi bi-check2-circle me-1"></i> Simpan Tindak Lanjut
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                Tidak ada siswa di kelas wali Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection