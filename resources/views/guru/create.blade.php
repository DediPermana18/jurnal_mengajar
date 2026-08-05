<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Guru</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">

    <h2>Tambah Guru Baru</h2>
    <a href="{{ route('guru.index') }}" class="btn btn-secondary mb-3">Kembali</a>

    <form action="{{ route('guru.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label">NIP</label>
            <input type="text" name="nip" class="form-control" placeholder="Masukkan NIP (Opsional)">
        </div>
        <div class="mb-3">
            <label class="form-label">Nama Guru</label>
            <input type="text" name="nama_guru" class="form-control" required placeholder="Masukkan Nama Lengkap">
        </div>
        <div class="mb-3">
            <label class="form-label">No HP</label>
            <input type="text" name="no_hp" class="form-control" placeholder="Masukkan No HP">
        </div>
        <button type="submit" class="btn btn-success">Simpan Data</button>
    </form>

</body>
</html>