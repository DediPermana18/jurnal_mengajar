<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jurnal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jurnal';

    protected $fillable = [
        'id_jadwal',
        'id_guru',
        'id_guru_pengganti',
        'status_kehadiran',
        'tanggal',
        'materi',
        'catatan_kejadian',
        'foto_kegiatan',
        'waktu_isi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'   => 'date',
            'waktu_isi' => 'datetime',
        ];
    }

    /**
     * Relasi ke User (Guru asli)
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke User (Guru Piket / Pengganti)
     */
    public function guruPengganti(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru_pengganti', 'id');
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'id_jadwal', 'id');
    }

    /**
     * Alias relasi ke Jadwal Pelajaran
     */
    public function jadwal(): BelongsTo
    {
        return $this->jadwalPelajaran();
    }

    /**
     * Relasi ke Absensi Jurnal
     */
    public function absensiJurnal(): HasMany
    {
        return $this->hasMany(AbsensiJurnal::class, 'id_jurnal', 'id');
    }

    /**
     * Alias relasi ke Absensi Jurnal
     */
    public function absensi(): HasMany
    {
        return $this->absensiJurnal();
    }

    /**
     * Hitung logika status pengisian jurnal & keterlambatan (Real-Time Check).
     * 
     * Aturan:
     * JIKA BELUM TERISI:
     * - Tanggal KBM == Hari ini & waktu saat ini > jam_selesai -> "Belum Terisi (Terlambat)" (Merah)
     * - Tanggal KBM == Hari ini & waktu saat ini <= jam_selesai -> "Belum Terisi" (Kuning)
     * - Tanggal KBM < Hari ini -> "Belum Terisi (Terlambat)" (Merah)
     * 
     * JIKA SUDAH TERISI:
     * - jam_isi <= jam_selesai (pada Hari H) -> "Sudah Terisi" (Hijau)
     * - jam_isi > jam_selesai atau diisi di Hari H+1 dst -> "Terisi (Terlambat)" (Oranye)
     */
    public static function hitungStatusPengisian(?Jurnal $jurnal, string $tanggalKbm, ?string $jamSelesaiStr = null): array
    {
        $now = \Carbon\Carbon::now();
        $todayStr = $now->toDateString();
        $nowTimeStr = $now->format('H:i:s');
        $kbmDateStr = \Carbon\Carbon::parse($tanggalKbm)->toDateString();

        $jamSelesaiTime = $jamSelesaiStr ? \Carbon\Carbon::parse($jamSelesaiStr)->format('H:i:s') : null;

        if (!$jurnal) {
            // Tanggal KBM sudah berlalu
            if ($kbmDateStr < $todayStr) {
                return [
                    'status'      => 'belum_terisi_terlambat',
                    'label'       => 'Belum Terisi (Terlambat)',
                    'badge_class' => 'bg-danger-subtle text-danger border border-danger-subtle',
                    'icon'        => 'bi-exclamation-octagon-fill',
                ];
            }

            // Tanggal KBM hari ini dan waktu server sudah melewati jam_selesai KBM
            if ($kbmDateStr === $todayStr && $jamSelesaiTime && $nowTimeStr > $jamSelesaiTime) {
                return [
                    'status'      => 'belum_terisi_terlambat',
                    'label'       => 'Belum Terisi (Terlambat)',
                    'badge_class' => 'bg-danger-subtle text-danger border border-danger-subtle',
                    'icon'        => 'bi-exclamation-octagon-fill',
                ];
            }

            return [
                'status'      => 'belum_terisi',
                'label'       => 'Belum Terisi',
                'badge_class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'icon'        => 'bi-clock-history',
            ];
        }

        // Jurnal Sudah Terisi
        $waktuIsi = $jurnal->waktu_isi ?? $jurnal->created_at ?? $jurnal->updated_at;
        $inputDateStr = \Carbon\Carbon::parse($waktuIsi)->toDateString();
        $inputTimeStr = \Carbon\Carbon::parse($waktuIsi)->format('H:i:s');

        // Diisi pada hari setelah tanggal KBM (Hari H+1 dst)
        if ($inputDateStr > $kbmDateStr) {
            return [
                'status'      => 'terisi_terlambat',
                'label'       => 'Terisi (Terlambat)',
                'badge_class' => 'bg-orange-subtle text-orange border border-orange-subtle',
                'style'       => 'background-color: #fff7ed; color: #c05500; border: 1px solid #fed7aa;',
                'icon'        => 'bi-clock-fill',
            ];
        }

        // Diisi pada Hari H tetapi jam pengisian melewati jam_selesai KBM
        if ($inputDateStr === $kbmDateStr && $jamSelesaiTime && $inputTimeStr > $jamSelesaiTime) {
            return [
                'status'      => 'terisi_terlambat',
                'label'       => 'Terisi (Terlambat)',
                'badge_class' => 'bg-orange-subtle text-orange border border-orange-subtle',
                'style'       => 'background-color: #fff7ed; color: #c05500; border: 1px solid #fed7aa;',
                'icon'        => 'bi-clock-fill',
            ];
        }

        return [
            'status'      => 'sudah_terisi',
            'label'       => 'Sudah Terisi',
            'badge_class' => 'bg-success-subtle text-success border border-success-subtle',
            'icon'        => 'bi-check-circle-fill',
        ];
    }

    /**
     * Accessor $jurnal->status_info untuk memuat info status pengisian secara instan
     */
    public function getStatusInfoAttribute(): array
    {
        $tanggalKbm = $this->tanggal ? $this->tanggal->toDateString() : \Carbon\Carbon::today()->toDateString();
        $jamSelesai = $this->jadwalPelajaran?->jamPelajaran?->jam_selesai;
        return static::hitungStatusPengisian($this, $tanggalKbm, $jamSelesai);
    }
}
