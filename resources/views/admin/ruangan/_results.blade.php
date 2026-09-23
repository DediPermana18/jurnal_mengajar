{{-- ============================================================ --}}
{{-- PARTIAL: Hasil filter Data Master Ruangan (di-update via AJAX) --}}
{{-- Dipakai oleh admin.ruangan.index (render awal) & RuanganController saat request AJAX (return JSON html). --}}
{{-- ============================================================ --}}

<div class="table-card-custom mb-4">
    <div class="table-responsive w-full overflow-x-auto">
        <table class="table table-custom align-middle min-w-full">
            <thead>
                <tr>
                    <th class="whitespace-nowrap" style="width: 5%;">NO</th>
                    <th class="whitespace-nowrap" style="width: 12%;">KODE</th>
                    <th class="whitespace-nowrap" style="width: 20%;">NAMA RUANGAN</th>
                    <th style="width: 20%;">LOKASI / GEDUNG</th>
                    <th style="width: 18%;">KELAS / JADWAL</th>
                    <th style="width: 18%;">PENGURUS</th>
                    <th class="text-center whitespace-nowrap" style="width: 10%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataRuangan as $ruangan)
                    <tr>
                        <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap">
                            <span class="badge bg-light text-dark border px-3 py-2 rounded-3 font-monospace">{{ $ruangan->kode_ruangan }}</span>
                        </td>
                        <td class="fw-semibold text-dark">{{ $ruangan->nama_ruangan }}</td>
                        <td class="text-muted">{{ $ruangan->lokasi ?? '-' }}</td>
                        <td>
                            @php
                                $kelasDipakai = $ruangan->jadwalPelajaran
                                    ->map(fn($jp) => $jp->kelas)
                                    ->filter()
                                    ->unique('id');
                            @endphp
                            @if($kelasDipakai->isEmpty())
                                <span class="text-muted small">-</span>
                            @else
                                <div class="flex flex-nowrap gap-1.5 overflow-x-auto max-w-[220px] py-1 scrollbar-thin">
                                    @foreach($kelasDipakai as $kelas)
                                        @php
                                            $namaLengkapKelas = ($kelas->tingkat && !str_starts_with($kelas->nama_kelas, $kelas->tingkat . ' '))
                                                ? $kelas->tingkat . ' ' . $kelas->nama_kelas
                                                : $kelas->nama_kelas;
                                        @endphp
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 whitespace-nowrap" style="font-size: 0.75rem;">
                                            {{ $namaLengkapKelas }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($ruangan->pengurus->isEmpty())
                                <span class="text-muted small">-</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($ruangan->pengurus as $pengurus)
                                        <span class="badge bg-primary-subtle text-primary border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $pengurus->nama }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                    <a href="{{ route('ruangan.edit', $ruangan->id) }}" class="btn btn-sm btn-warning text-white rounded-3 px-2 py-1" title="Edit ruangan">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form action="{{ route('ruangan.destroy', $ruangan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ruangan ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" title="Hapus ruangan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-building fs-1 d-block mb-2"></i>
                            Belum ada data ruangan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>