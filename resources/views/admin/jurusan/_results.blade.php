{{-- ============================================================ --}}
{{-- PARTIAL: Hasil filter Data Master Jurusan (di-update via AJAX) --}}
{{-- Dipakai oleh admin.jurusan.index (render awal) & JurusanController saat request AJAX (return JSON html). --}}
{{-- ============================================================ --}}

<div class="table-card-custom mb-4">
    <div class="table-responsive w-full overflow-x-auto">
        <table class="table table-custom align-middle min-w-full">
            <thead>
                <tr>
                    <th class="whitespace-nowrap" style="width: 10%;">NO</th>
                    <th class="whitespace-nowrap" style="width: 25%;">KODE JURUSAN</th>
                    <th class="whitespace-nowrap">NAMA JURUSAN</th>
                    <th class="text-end whitespace-nowrap" style="width: 20%;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dataJurusan as $jurusan)
                    <tr>
                        <td class="whitespace-nowrap">{{ $loop->iteration }}</td>
                        <td class="whitespace-nowrap"><span class="badge bg-light text-dark border px-3 py-2 rounded-3 font-monospace">{{ $jurusan->kode_jurusan }}</span></td>
                        <td class="fw-semibold text-dark">{{ $jurusan->nama_jurusan }}</td>
                        <td class="text-end whitespace-nowrap">
                            @if(in_array(auth()->user()->role ?? '', ['admin_tu', 'admin', 'super_admin']) || (auth()->user() && auth()->user()->isTestingUser()))
                                <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                <a href="{{ route('jurusan.edit', $jurusan->id) }}" class="btn btn-sm btn-outline-warning rounded-3" title="Edit jurusan">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form action="{{ route('jurusan.destroy', $jurusan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data jurusan ini? Data yang sudah dihapus tidak dapat dipulihkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Hapus jurusan">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>Belum ada data jurusan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>