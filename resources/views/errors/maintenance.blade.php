<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f172a">
    <title>Sistem Sedang Dalam Pemeliharaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #e2e8f0;
            background: radial-gradient(1200px 600px at 20% 0%, #1e293b 0%, #0f172a 45%, #020617 100%);
        }

        .maintenance-card {
            max-width: 520px;
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(8px);
            box-shadow: 0 24px 60px rgba(2, 6, 23, 0.55);
        }

        .maintenance-icon {
            width: 78px;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border-radius: 1.1rem;
            font-size: 2rem;
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.12);
            border: 1px solid rgba(251, 191, 36, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.06); }
        }

        .bg-soft {
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.14);
        }

        code {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.10);
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="maintenance-card p-4 p-md-5 text-center">
            <div class="maintenance-icon mb-4">
                <i class="bi bi-wrench-adjustable-circle-fill"></i>
            </div>

            <span class="badge rounded-pill px-3 py-2 mb-3" style="background: rgba(251,191,36,0.12); color:#fbbf24; border:1px solid rgba(251,191,36,0.3); font-size:0.72rem; letter-spacing:0.08em;">
                STATUS HTTP 503
            </span>

            <h1 class="h3 fw-bold mb-2" style="color:#f8fafc; letter-spacing:-0.02em;">
                Sistem Sedang Dalam Pemeliharaan
            </h1>
            <p class="text-muted mb-4" style="color:#94a3b8 !important;">
                Kami sedang melakukan perbaikan dan peningkatan sistem untuk pengalaman yang lebih baik.
                Halaman ini akan aktif kembali dalam beberapa saat.
            </p>

            <div class="bg-soft rounded-4 p-3 text-start mb-4">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="bi bi-info-circle-fill text-warning fs-5"></i>
                    <span class="fw-semibold text-light">Yang dapat Anda lakukan</span>
                </div>
                <ul class="small text-muted mb-0 ps-4" style="color:#cbd5e1 !important;">
                    <li>Simpan dan cek kembali pekerjaan Anda setelah sistem aktif kembali.</li>
                    <li>Hubungi tim administrator / Petugas IT bila membutuhkan bantuan.</li>
                    <li>Halaman ini diperiksa ulang secara otomatis setiap saat.</li>
                </ul>
            </div>

            <div class="text-muted small" style="color:#94a3b8 !important;">
                <i class="bi bi-clock-history me-1"></i>
                Terakhir diperiksa:
                <span id="last-check">{{ now()->translatedFormat('d M Y, H:i:s') }}</span>
            </div>
        </div>
    </div>

    <script>
        // Periksa ulang status sistem secara berkala: bila maintenance selesai,
        // halaman otomatis memuat ulang kembali ke aplikasi.
        setTimeout(function () {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>