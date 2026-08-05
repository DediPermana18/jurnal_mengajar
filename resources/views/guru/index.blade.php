<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Guru</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">

    <h2>Daftar Guru</h2>
    <a href="{{ route('guru.create') }}" class="btn btn-primary mb-3">+ Tambah Guru</a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>No</th>
                <th>NIP</th>
                <th>Nama Guru</th>
                <th>No HP</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dataGuru as $key => $guru)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $guru->nip ?? '-' }}</td>
                    <td>{{ $guru->nama_guru }}</td>
                    <td>{{ $guru->no_hp ?? '-' }}</td>
                    <td>
                        <a href="{{ route('guru.edit', $guru->id_guru) }}" class="btn btn-warning btn-sm">Edit</a>
                        <form action="{{ route('guru.destroy', $guru->id_guru) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus data ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Data guru masih kosong.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>