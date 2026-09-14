<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Induk transaksi dispensasi kolektif (rombongan): satu pengajuan surat
 * dispensasi untuk banyak siswa. Detail yang dipakai bersama (tanggal, tipe,
 * jam pelajaran, alasan, TTD Guru Piket) disimpan di sini, sedangkan tiap
 * siswa disimpan sebagai baris DispensasiSiswa melalui dispensasi_kolektif_id
 * lengkap dengan tanda tangan digital (TTD) per siswa.
 */
class DispensasiKolektif extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'dispensasi_kolektif';

    protected $fillable = [
        'id_guru_piket',
        'id_jadwal',
        'id_guru',
        'tanggal',
        'tipe_dispen',
        'jam_ke',
        'jam_keluar_jp',
        'jam_masuk_jp',
        'jam_kembali_jp',
        'tidak_kembali_hari_ini',
        'alasan',
        'status',
        'approved_at',
        'approved_by',
        'ttd_guru',
        'approval_token',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'approved_at' => 'datetime',
        'tidak_kembali_hari_ini' => 'boolean',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Relasi ke Guru Piket yang menerbitkan dispensasi kolektif.
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
     * Relasi ke user yang menyetujui pengajuan kolektif (Guru Piket).
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Baris anak: daftar siswa beserta TTD digital masing-masing.
     */
    public function siswaItems(): HasMany
    {
        return $this->hasMany(DispensasiSiswa::class, 'dispensasi_kolektif_id', 'id')->orderBy('id');
    }

    /**
     * Nomor surat kolektif, mis. DIS-0001/2026.
     */
    public function getNomorSuratAttribute(): string
    {
        return 'DIS-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT)
            .'/'.($this->tanggal?->format('Y') ?? now()->year);
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
     * Label ringkas jam ke, mis. "Jam 1 - 4, 11".
     */
    public function getJamKeLabelAttribute(): string
    {
        return 'Jam '.DispensasiSiswa::formatJamKeList($this->jam_ke_list);
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function getStatusLabelAttribute(): string
    {
        return DispensasiSiswa::STATUS_LABELS[$this->status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * Badge bootstrap untuk status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return DispensasiSiswa::STATUS_BADGES[$this->status]
            ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    /**
     * Label tipe dispensasi, mis. "Masuk Kelas" / "Keluar Gerbang".
     */
    public function getTipeDispenLabelAttribute(): string
    {
        return DispensasiSiswa::TIPE_LABELS[$this->tipe_dispen ?? DispensasiSiswa::TIPE_KELUAR]
            ?? ucfirst((string) $this->tipe_dispen);
    }

    /**
     * Apakah dispensasi kolektif bertipe "Masuk Kelas"?
     */
    public function isTipeMasuk(): bool
    {
        return $this->tipe_dispen === DispensasiSiswa::TIPE_MASUK;
    }

    /**
     * Apakah rombongan dicatat "Tidak Kembali Hari Ini" (izin hingga pulang).
     */
    public function isTidakKembaliHariIni(): bool
    {
        return (bool) $this->tidak_kembali_hari_ini;
    }

    /**
     * Konfirmasi kembali dikelola per-siswa (item anak).
     * Pada level induk selalu false agar baris "Rencana kembali" tampil secara tepat.
     */
    public function isKembali(): bool
    {
        return false;
    }

    /**
     * Jumlah siswa pada rombongan ini.
     */
    public function getJumlahSiswaAttribute(): int
    {
        return $this->siswaItems->count();
    }

    /**
     * URL tampilan tanda tangan Guru Piket (penyetuju) atau null.
     */
    public function getTtdGuruUrlAttribute(): ?string
    {
        $ttd = $this->ttd_guru ? trim((string) $this->ttd_guru) : null;

        if (! $ttd) {
            return null;
        }

        return preg_match('/^data:/i', $ttd)
            ? $ttd
            : Storage::disk('public')->url($ttd);
    }

    /**
     * Apakah Guru Piket sudah menandatangani (canvas TTD) saat membuat pengajuan?
     */
    public function getHasTtdGuruAttribute(): bool
    {
        return (bool) $this->ttd_guru_url;
    }
}
