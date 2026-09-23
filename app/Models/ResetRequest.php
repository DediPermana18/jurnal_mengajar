<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pengajuan reset kredensial (lupa sandi / lupa kode aktivasi).
 *
 * Alur:
 *  1. Pemohon (guest) mengisi formulir publik + tanda tangan digital canvas
 *     -> record berstatus 'pending'.
 *  2. Admin TU memverifikasi tanda tangan di panel /admin/pengajuan-reset:
 *     - Setujui -> status 'approved' + reset_token unik (berlaku 24 jam).
 *     - Tolak   -> status 'rejected' + admin_note (alasan).
 *  3. Pemohon membuka /reset-credentials/{token} (tanpa login), lalu mengisi
 *     kredensial baru sesuai jenis_pengajuan. Token di-invalidate setelah
 *     pemakaian (reset_token & kata kunci token_expires_at di-null-kan).
 *
 * Menghormati isolasi data testing via trait HasTestingData.
 */
class ResetRequest extends Model
{
    use HasFactory, HasTestingData;

    public const JENIS_LUPA_SANDI = 'lupa_sandi';

    public const JENIS_LUPA_KODE_AKTIVASI = 'lupa_kode_aktivasi';

    public const JENIS_OPTIONS = [
        self::JENIS_LUPA_SANDI => 'Lupa Sandi',
        self::JENIS_LUPA_KODE_AKTIVASI => 'Lupa Kode Aktivasi',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        self::STATUS_APPROVED => 'bg-success-subtle text-success border border-success-subtle',
        self::STATUS_REJECTED => 'bg-danger-subtle text-danger border border-danger-subtle',
    ];

    /**
     * Masa berlaku token reset sejak disetujui (jam).
     */
    public const TOKEN_TTL_HOURS = 24;

    protected $table = 'reset_requests';

    protected $fillable = [
        'user_id',
        'jenis_pengajuan',
        'tanda_tangan',
        'status',
        'reset_token',
        'token_expires_at',
        'admin_note',
        'is_testing_data',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_testing_data' => 'boolean',
    ];

    /**
     * User pemohon.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Label jenis pengajuan yang ramah tampilan.
     */
    public function getJenisLabelAttribute(): string
    {
        return self::JENIS_OPTIONS[$this->jenis_pengajuan] ?? ucfirst(str_replace('_', ' ', (string) $this->jenis_pengajuan));
    }

    /**
     * Label status pengajuan.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Kelas badge Bootstrap sesuai status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Token masih sah untuk membuka halaman reset: status approved + token ada
     * + belum kedaluwarsa (24 jam sejak disetujui).
     */
    public function isValidToken(): bool
    {
        return $this->isApproved()
            && ! empty($this->reset_token)
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }

    /**
     * URL khusus ber-token untuk reset kredensial (tanpa login).
     */
    public function resetUrl(): ?string
    {
        if (empty($this->reset_token)) {
            return null;
        }

        return url('/reset-credentials/'.$this->reset_token);
    }

    /**
     * Tautan WhatsApp berisi pesan + link reset untuk dikirim Admin TU ke
     * nomor HP pemohon. Null bila nomor HP pemohon tidak tersedia / token sah.
     */
    public function waResetUrl(): ?string
    {
        if (! $this->isValidToken() || ! $this->user) {
            return null;
        }

        $noHp = $this->user->noHpInternasional();
        if ($noHp === '') {
            return null;
        }

        $nama = $this->user->nama ?? 'Bapak/Ibu';
        $pesan = "Halo {$nama},\n\n"
            ."Pengajuan *{$this->jenis_label}* Anda telah disetujui oleh Admin TU.\n"
            .'Silakan buka tautan berikut untuk mengatur ulang (berlaku 24 jam):'."\n"
            .$this->resetUrl()."\n\n"
            .'Jangan bagikan tautan ini kepada siapa pun. Terima kasih.';

        return 'https://wa.me/'.$noHp.'?text='.rawurlencode($pesan);
    }

    /**
     * Cari pengajuan berdasarkan token reset. Null bila tidak ditemukan.
     * Menghormati TestingDataScope (guest/non-IT hanya melihat data real).
     */
    public static function resolveToken(?string $token): ?self
    {
        if (empty($token)) {
            return null;
        }

        return static::where('reset_token', $token)->first();
    }

    /**
     * Generate token reset unik yang belum pernah dipakai.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('reset_token', $token)->exists());

        return $token;
    }
}