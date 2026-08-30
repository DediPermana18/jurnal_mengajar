<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DispensasiSiswa extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK   = 'ditolak';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Pending',
        self::STATUS_DISETUJUI => 'Disetujui',
        self::STATUS_DITOLAK   => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING   => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_DISETUJUI => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_DITOLAK   => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    public const JENIS_KELUAR    = 'keluar_gerbang';
    public const JENIS_SAKIT     = 'sakit';
    public const JENIS_KEPERLUAN = 'keperluan';
    public const JENIS_ACARA     = 'acara_sekolah';

    public const JENIS_LABELS = [
        self::JENIS_KELUAR    => 'Keluar Gerbang Sekolah',
        self::JENIS_SAKIT     => 'Sakit / Pulang',
        self::JENIS_KEPERLUAN => 'Keperluan Pribadi / Keluarga',
        self::JENIS_ACARA     => 'Tugas / Acara Sekolah',
    ];

    protected $table = 'dispensasi_siswa';

    protected $fillable = [
        'id_siswa',
        'id_guru_piket',
        'id_jadwal',
        'id_guru',
        'tanggal',
        'jenis',
        'jam_ke',
        'alasan',
        'status',
        'approved_at',
        'approved_by',
        'catatan_penolakan',
        'ttd_siswa',
        'bukti_surat',
        'approval_token',
        'ttd_waka',
        'keluar_gerbang_at',
        'keluar_gerbang_by',
    ];

    protected $casts = [
        'tanggal'           => 'date',
        'approved_at'       => 'datetime',
        'keluar_gerbang_at' => 'datetime',
    ];

    /**
     * Relasi ke data Siswa yang di-dispensasi.
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id');
    }

    /**
     * Relasi ke Guru Piket yang menerbitkan dispensasi.
     */
    public function guruPiket(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru_piket', 'id');
    }

    /**
     * Relasi ke slot Jadwal Pelajaran (mapel/guru) yang ditinggalkan.
     */
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'id_jadwal', 'id');
    }

    /**
     * Relasi ke Guru Mapel yang mengajar pada jadwal yang ditinggalkan.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke User (Guru Piket / Admin) yang menyetujui pengajuan.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Relasi ke akun Satpam yang mengizinkan siswa keluar gerbang.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keluar_gerbang_by', 'id');
    }

    /**
     * Apakah pengajuan sudah disetujui?
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_DISETUJUI;
    }

    /**
     * Apakah siswa sudah diizinkan keluar gerbang oleh Satpam?
     */
    public function isKeluarGerbang(): bool
    {
        return $this->keluar_gerbang_at !== null;
    }

    /**
     * Nomor surat resmi surat dispensasi, mis. DIS-0001/2026.
     */
    public function getNomorSuratAttribute(): string
    {
        return 'DIS-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT)
            . '/' . ($this->tanggal?->format('Y') ?? now()->year);
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * Badge bootstrap untuk status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    /**
     * Label jenis dispensasi dalam Bahasa Indonesia.
     */
    public function getJenisLabelAttribute(): string
    {
        return self::JENIS_LABELS[$this->jenis] ?? ucfirst(str_replace('_', ' ', (string) $this->jenis));
    }

    /**
     * Daftar jam ke dalam bentuk array, mis. "3,4,5" -> [3, 4, 5].
     */
    public function getJamKeListAttribute(): array
    {
        return collect(explode(',', (string) $this->jam_ke))
            ->map(fn ($j) => (int) trim($j))
            ->filter(fn ($j) => $j > 0)
            ->values()
            ->all();
    }

    /**
     * Label ringkas jam ke, mis. "Jam 3, 4, 5".
     */
    public function getJamKeLabelAttribute(): string
    {
        return 'Jam ' . implode(', ', $this->jam_ke_list);
    }

    /**
     * URL tampilan tanda tangan siswa (canvas / data URL base64) atau null.
     */
    public function getTtdUrlAttribute(): ?string
    {
        $ttd = $this->ttd_siswa ? trim((string) $this->ttd_siswa) : null;

        if (!$ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah siswa sudah menandatangani (canvas TTD) pada pengajuan ini?
     */
    public function getHasTtdAttribute(): bool
    {
        return (bool) $this->ttd_url;
    }

    /**
     * URL tampilan tanda tangan Waka Kesiswaan / Penyetuju (base64 data URL) atau null.
     */
    public function getTtdWakaUrlAttribute(): ?string
    {
        $ttd = $this->ttd_waka ? trim((string) $this->ttd_waka) : null;

        if (!$ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah Waka Kesiswaan / Penyetuju sudah menandatangani melalui link approval publik?
     */
    public function getHasTtdWakaAttribute(): bool
    {
        return (bool) $this->ttd_waka_url;
    }

    /**
     * URL publik link approval (tanpa login) untuk Waka Kesiswaan / Penyetuju.
     */
    public function getApprovalUrlAttribute(): ?string
    {
        return $this->approval_token ? url('/approve-dispen/' . $this->approval_token) : null;
    }

    /**
     * Path file bukti surat dispen hasil upload (JPG/PNG/PDF),
     * disimpan pada kolom bukti_surat (storage/app/public/dispensasi/).
     */
    public function getBuktiPathAttribute(): ?string
    {
        return $this->bukti_surat ? trim((string) $this->bukti_surat) : null;
    }

    /**
     * URL publik untuk mengakses file bukti surat dispen.
     */
    public function getBuktiUrlAttribute(): ?string
    {
        return $this->bukti_path ? Storage::disk('public')->url($this->bukti_path) : null;
    }

    /**
     * Tipe file bukti surat: 'pdf', 'image', atau null jika tidak ada.
     */
    public function getBuktiTypeAttribute(): ?string
    {
        if (!$this->bukti_path) {
            return null;
        }

        $ext = strtolower(pathinfo($this->bukti_path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png'], true) ? 'image' : 'pdf';
    }

    /**
     * Jurnal pada tanggal dispensa yang jam pelajarannya cocok dengan jam dispen.
     */
    public function jurnalTerkait()
    {
        $tanggal    = $this->tanggal?->toDateString();
        $jadwalIds  = $this->jadwalIdsTerkait();

        return $jadwalIds->isEmpty() || !$tanggal
            ? collect()
            : Jurnal::whereIn('id_jadwal', $jadwalIds)->whereDate('tanggal', $tanggal)->get();
    }

    /**
     * ID jadwal pelajaran milik kelas siswa yang jam ke-nya termasuk dalam jam dispen.
     */
    public function jadwalIdsTerkait()
    {
        if (!$this->siswa?->id_kelas) {
            return collect();
        }

        $jamKeList = $this->jam_ke_list;

        return JadwalPelajaran::with('jamPelajaran')
            ->where('id_kelas', $this->siswa->id_kelas)
            ->get()
            ->filter(fn (JadwalPelajaran $j) => in_array((int) $j->jamPelajaran?->jam_ke, $jamKeList, true))
            ->pluck('id');
    }

    /**
     * Terapkan status "Dispen" otomatis ke absensi_jurnal pada jurnal yang sudah dibuat.
     * Mengembalikan jumlah baris absensi yang disinkronkan.
     */
    public function terapkanKeAbsensi(): int
    {
        $idSiswa = (int) $this->id_siswa;
        $alasan  = trim((string) $this->alasan);
        $count   = 0;

        foreach ($this->jurnalTerkait() as $jurnal) {
            AbsensiJurnal::updateOrCreate(
                ['id_jurnal' => $jurnal->id, 'id_siswa' => $idSiswa],
                [
                    'status'     => 'Dispen',
                    'keterangan' => 'Dispensasi: ' . $alasan,
                    'foto_surat' => $this->bukti_path,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Cabut status "Dispen" hasil integrasi (hanya baris yang dibuat otomatis).
     * Digunakan saat pengajuan ditolak / dicabut.
     */
    public function cabutDariAbsensi(): int
    {
        $idSiswa = (int) $this->id_siswa;
        $count   = 0;

        foreach ($this->jurnalTerkait() as $jurnal) {
            $row = AbsensiJurnal::where('id_jurnal', $jurnal->id)
                ->where('id_siswa', $idSiswa)
                ->where('status', 'Dispen')
                ->where('keterangan', 'like', 'Dispensasi:%')
                ->first();

            if ($row) {
                $row->update(['status' => 'Hadir', 'keterangan' => null, 'foto_surat' => null]);
                $count++;
            }
        }

        return $count;
    }
}