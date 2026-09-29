<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use App\Models\Concerns\TestingDataContextAware;
use App\Models\Scopes\ActiveTahunAjaranScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class JamPelajaran extends Model implements TestingDataContextAware
{
    use HasFactory, HasTestingData, SoftDeletes;

    protected $table = 'jam_pelajaran';

    protected $fillable = [
        'hari',
        'kategori_hari',
        'shift_id',
        'jam_ke',
        'jam_mulai',
        'jam_selesai',
        'jenis',
        'tahun_ajaran_id',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Konsumen runtime hanya melihat slot TA aktif (+ slot legacy).
        static::addGlobalScope(new ActiveTahunAjaranScope);

        static::saving(function ($jam) {
            if (empty($jam->hari)) {
                $kat = $jam->kategori_hari ?? 'Senin-Kamis';
                $jam->hari = ($kat === 'Jumat') ? 'Jumat' : 'Senin';
            }
            if (empty($jam->kategori_hari)) {
                $jam->kategori_hari = in_array($jam->hari, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';
            }
        });
    }

    /**
     * Scope: slot yang terlihat oleh kelas dengan shift $shiftId.
     * Kelas selalu melihat slot Global (shift_id NULL) + slot shift-nya sendiri.
     */
    public function scopeForShift($query, ?int $shiftId)
    {
        return $query->where(function ($q) use ($shiftId) {
            $q->whereNull('shift_id')
                ->orWhere('shift_id', $shiftId);
        });
    }

    /**
     * Scope: slot milik satu shift tertentu (termasuk Global = NULL).
     */
    public function scopeOfShift($query, ?int $shiftId)
    {
        if ($shiftId === null || $shiftId === 0) {
            return $query->whereNull('shift_id');
        }

        return $query->where('shift_id', $shiftId);
    }

    /**
     * Scope: batasi slot ke konteks Tahun Ajaran & Semester tertentu.
     *
     * @param  int|null  $tahunAjaranId  id tahun_ajaran; null = hanya slot legacy (tanpa TA).
     * @param  bool  $includeLegacy  sertakan slot legacy (tahun_ajaran_id NULL) — dipakai
     *                               khusus untuk Tahun Ajaran yang sedang AKTIF, karena
     *                               data lama dipandang sebagai kepunyaan TA berjalan.
     */
    public function scopeOfTahunAjaran($query, ?int $tahunAjaranId, bool $includeLegacy = false)
    {
        if ($tahunAjaranId === null) {
            return $query->whereNull('tahun_ajaran_id');
        }

        return $query->where(function ($q) use ($tahunAjaranId, $includeLegacy) {
            $q->where('tahun_ajaran_id', $tahunAjaranId);
            if ($includeLegacy) {
                $q->orWhereNull('tahun_ajaran_id');
            }
        });
    }

    /**
     * Query slot KBM yang bisa di-plot: punya jam_ke, bukan slot istirahat,
     * dan berada pada shift + Tahun Ajaran yang diminta.
     *
     * Seluruh konsumen plotting (index matriks, store, update, import, dashboard)
     * WAJIB membangun target lewat query ini — TIDAK BOLEH memakai ID jam dari
     * request/frontend, karena ID `jam_pelajaran` bersifat auto-increment dan
     * berubah total setiap master jam dihapus & dibuat ulang.
     */
    public function scopePlotable($query, ?int $shiftId = null, ?int $tahunAjaranId = null, bool $includeLegacyTa = false)
    {
        return $query->ofShift($shiftId)
            ->ofTahunAjaran($tahunAjaranId, $includeLegacyTa)
            ->whereNotNull('jam_ke')
            ->where('jenis', '!=', 'istirahat');
    }

    /**
     * Urutan kanonik slot jam: KRONOLOGIS berdasarkan WAKTU MULAI.
     *
     * INI urutan yang dipakai seluruh aplikasi (matriks plotting, dropdown,
     * master jam, dashboard). Alasannya: `jam_ke` TIDAK selalu terisi.
     * Slot non-KBM seperti Istirahat 1 (09.40-10.00) dan Istirahat 2
     * (12.00-13.00) sengaja ber-`jam_ke` NULL karena bukan jam pelajaran
     * uttered — bila sorting memakai `jam_ke` lebih dulu, semua slot istirahat
     * terseret ke baris paling bawah tabel, padahal secara waktu jelas berada
     * di tengah-tengah jam KBM. `id` juga tidak boleh dipakai karena
     * auto-increment dan bergeser total saat master jam dihapus-dibuat ulang.
     *
     * Rantai tie-breaker: jam_mulai -> jam_selesai -> jam_ke -> id (terbaru).
     */
    public function scopeUrutkanWaktu($query)
    {
        return $query
            ->orderBy('jam_mulai')
            ->orderBy('jam_selesai')
            // Waktu identik: slot bernomor (jam_ke) didahulukan, slot
            // non-KBM (jam_ke NULL) menyusul — bukan sebaliknya.
            ->orderByRaw('jam_ke IS NULL')
            ->orderBy('jam_ke')
            ->orderByDesc('id');
    }

    /**
     * Resolver slot KBM kanonik.
     *
     * Mencari master `jam_pelajaran` yang AKTIF untuk kombinasi
     * (hari, jam_ke, shift, tahun_ajaran) — inilah satu-satunya sumber
     * kebenaran `id_jam` yang boleh dipakai saat menyimpan jadwal.
     *
     * Mengabaikan global scope Tahun Ajaran (slot arsip ikut dipertimbangkan
     * sebagai sumber ketika runtime tidak punya slot aktif) dan tidak melempar
     * error: bila slot yang diminta sedang aktif TIDAK ada, method ini tetap
     * mengembalikan slot cadangan pada shift yang sama agar data jadwal lama
     * tidak hilang (fallback "gantung"), dan pemanggil dapat mendeteksinya
     * lewat {@see static::isSlotPlausibel()}.
     *
     * @param  int|null  $jamKe  nomor urut jam ke-N (slot ke-1, ke-2, ...).
     * @param  string  $hari  'Senin'..'Jumat'.
     * @param  int|null  $shiftId  shift efektif kelas; null = slot Global.
     * @param  int|null  $tahunAjaranId  id tahun_ajaran konteks.
     * @param  bool  $includeLegacyTa  sertakan slot ber-TA NULL (TA aktif).
     */
    public static function resolveSlot(
        ?int $jamKe,
        string $hari,
        ?int $shiftId = null,
        ?int $tahunAjaranId = null,
        bool $includeLegacyTa = false
    ): ?self {
        if ($jamKe === null || $jamKe <= 0 || $hari === '') {
            return null;
        }

        $base = static::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKe);

        $exact = (clone $base)->plotable($shiftId, $tahunAjaranId, $includeLegacyTa)
            ->urutkanWaktu()
            ->first();

        if ($exact) {
            return $exact;
        }

        // Fallback 1: bila shift yang diminta TIDAK punya slot sama sekali
        // untuk hari tersebut, return null (jangan menebak slot milik shift lain).
        $adaSlotDiShift = (clone $base)->plotable($shiftId, $tahunAjaranId, $includeLegacyTa)->exists();
        if ($adaSlotDiShift) {
            return null; // shift punya slot, tapi bukan untuk jam_ke ini → biarkan null.
        }

        // Fallback 2: cari slot KBM pada hari & jam_ke tsb milik shift mana pun
        // (dibatasi TA konteks) agar jadwal lama tetap punya slot valid.
        return (clone $base)
            ->plotable(null, $tahunAjaranId, $includeLegacyTa)
            ->urutkanWaktu()
            ->first();
    }

    /**
     * Bulk-map daftar (jam_ke => id slot) untuk satu hari + shift + TA.
     *
     * Dipakai consumer yang butuh memetakan banyak baris jadwal sekaligus
     * (auto-heal matriks plotting, dashboard guru) tanpa query N+1.
     *
     * @return Collection<int, JamPelajaran>
     */
    public static function mapSlotPerJamKe(
        string $hari,
        ?int $shiftId = null,
        ?int $tahunAjaranId = null,
        bool $includeLegacyTa = false
    ) {
        return static::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('hari', $hari)
            ->plotable($shiftId, $tahunAjaranId, $includeLegacyTa)
            ->urutkanWaktu()
            ->get()
            // Key = (int) jam_ke: bila ada slot ganda, yang paling baru
            // (id tertinggi) menang agar plotting konsisten dengan master jam
            // yang sedang aktif di layar admin.
            ->keyBy(fn ($slot) => (int) $slot->jam_ke);
    }

    /**
     * Master slot untuk SATU hari, diurutkan KRONOLOGIS (jam_mulai).
     *
     * Dipakai consumer yang perlu membangun matriks slot (Plotting kelas).
     * Urutan TIDAK bergantung pada `id` maupun `jam_ke`, sehingga:
     *  - master jam yang dibuat ulang (ID 91, 93, 97, ...) tetap tampil pada
     *    kolom yang benar;
     *  - slot Istirahat muncul di posisi waktunya (tengah-tengah jam KBM),
     *    bukan terseret ke paling bawah.
     *
     * @see scopeUrutkanWaktu()
     */
    public static function slotPerHari(
        string $hari,
        ?int $shiftId = null,
        ?int $tahunAjaranId = null,
        bool $includeLegacyTa = false
    ) {
        return static::withoutGlobalScope(ActiveTahunAjaranScope::class)
            ->where('hari', $hari)
            ->ofShift($shiftId)
            ->ofTahunAjaran($tahunAjaranId, $includeLegacyTa)
            ->urutkanWaktu()
            ->get();
    }

    /**
     * Cek apakah sebuah slot jam masih layak dipakai sebagai `id_jam`.
     *
     * Tidak layak bila: null, sudah soft-delete, hari tidak cocok, atau slot
     * milik shift yang berbeda dari shift efektif kelas saat ini — mis. jadwal
     * lama yang masih menunjuk master jam hasil delete-recreate.
     */
    public static function isSlotPlausibel(?self $slot, string $hari, ?int $shiftId = null): bool
    {
        if (! $slot) {
            return false;
        }

        if ($slot->deleted_at !== null) {
            return false;
        }

        if ($slot->hari !== $hari) {
            return false;
        }

        if ($shiftId !== null && $slot->shift_id !== null && (int) $slot->shift_id !== (int) $shiftId) {
            return false;
        }

        return true;
    }

    /**
     * Backward compatibility accessor for kategori_hari
     */
    public function getKategoriHariAttribute(): string
    {
        $h = $this->attributes['hari'] ?? null;
        if ($h && in_array($h, ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'], true)) {
            return in_array($h, ['Senin', 'Selasa', 'Rabu', 'Kamis'], true) ? 'Senin-Kamis' : 'Jumat';
        }
        $kat = $this->attributes['kategori_hari'] ?? 'Senin-Kamis';

        return ($kat === 'Jumat') ? 'Jumat' : 'Senin-Kamis';
    }

    protected $casts = [
        'jam_ke' => 'integer',
        'is_testing_data' => 'boolean',
    ];

    /**
     * Label jenis KBM yang ramah tampilan
     */
    public function getJenisLabelAttribute(): string
    {
        return match ($this->jenis) {
            'kbm' => 'KBM',
            'istirahat' => 'Istirahat',
            default => ucfirst($this->jenis ?? '-'),
        };
    }

    /**
     * Format jam_mulai & jam_selesai sebagai "HH.MM – HH.MM"
     */
    public function getRentangWaktuAttribute(): string
    {
        $mulai = substr($this->jam_mulai, 0, 5);
        $selesai = substr($this->jam_selesai, 0, 5);

        return str_replace(':', '.', $mulai).' – '.str_replace(':', '.', $selesai);
    }

    /**
     * Normalisasi nilai waktu penyimpanan menjadi format konsisten "HH:MM".
     * Sumber bisa berupa 'HH:MM:SS', 'HH:MM', datetime string ('YYYY-MM-DD HH:MM:SS'),
     * atau nilai korup dari versi lama. Nilai yang tidak dapat diparsing
     * ditampilkan sebagai '--:--' agar tidak pernah menghasilkan teks acak
     * seperti '10:00 - 07:'.
     */
    public static function formatJamHm(?string $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return '--:--';
        }

        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $m)) {
            $hour = min(23, max(0, (int) $m[1]));
            $minute = min(59, max(0, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '--:--';
    }

    /**
     * Label jam mulai konsisten HH:MM (opsi dropdown Agenda / Pembiasaan).
     */
    public function getJamMulaiLabelAttribute(): string
    {
        return static::formatJamHm($this->jam_mulai);
    }

    /**
     * Label jam selesai konsisten HH:MM (opsi dropdown Agenda / Pembiasaan).
     */
    public function getJamSelesaiLabelAttribute(): string
    {
        return static::formatJamHm($this->jam_selesai);
    }

    /**
     * Relasi ke Jadwal Pelajaran
     */
    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'id_jam', 'id');
    }

    /**
     * Relasi ke Shift (NULL = slot global / legacy).
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(ShiftPelajaran::class, 'shift_id', 'id');
    }

    /**
     * Relasi ke Tahun Ajaran & Semester (NULL = slot legacy / era sebelum fitur TA).
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}
