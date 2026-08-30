<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan KBM</title>
</head>
<body>
    <h3>LAPORAN KEGIATAN BELAJAR MENGAJAR (KBM)</h3>
    <div>{{ strtoupper(config('app.name', 'WebJournal Management System')) }}</div>
    <div>Periode: {{ $periodeMulai }} s.d. {{ $periodeSelesai }}</div>
    <br>

    <table border="1" cellspacing="0" cellpadding="4" style="border-collapse: collapse;">
        <tr>
            <th>Total Jam KBM Terlaksana</th>
            <th>Guru Hadir</th>
            <th>Guru Izin</th>
            <th>Guru Sakit</th>
            <th>Guru Dinas</th>
            <th>Jurnal Mengajar Terisi</th>
        </tr>
        <tr>
            <td>{{ $totalJamKBM }}</td>
            <td>{{ $guruHadir }}</td>
            <td>{{ $guruIzin }}</td>
            <td>{{ $guruSakit }}</td>
            <td>{{ $guruDinas }}</td>
            <td>{{ $totalJurnalTerisi }}</td>
        </tr>
    </table>

    <br>

    <table border="1" cellspacing="0" cellpadding="4" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam Ke-</th>
                <th>Kelas</th>
                <th>Guru</th>
                <th>Guru Pengganti</th>
                <th>Mata Pelajaran</th>
                <th>Materi / Jurnal</th>
                <th>Status Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($daftarJurnal as $idx => $jurnal)
                @php $jadwal = $jurnal->jadwalPelajaran; @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $jurnal->tanggal->translatedFormat('d/m/Y') }}</td>
                    <td>{{ $jadwal?->jam?->jam_ke ?? '-' }}</td>
                    <td>{{ $jadwal?->kelas?->nama_kelas ?? '-' }}</td>
                    <td>{{ $jurnal->guru?->nama ?? $jadwal?->guru?->nama ?? '-' }}</td>
                    <td>{{ $jurnal->guruPengganti?->nama ?? '-' }}</td>
                    <td>{{ $jadwal?->mapel?->nama_mapel ?? '-' }}</td>
                    <td>{{ $jurnal->materi ?: '-' }}</td>
                    <td>{{ $jurnal->status_kehadiran ?? 'Hadir' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Tidak ada data pada rentang yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>