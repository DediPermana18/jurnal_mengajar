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
use Illuminate\Support\Facades\DB;

class JadwalPelajaran extends Model implements TestingDataContextAware
{
    use HasFactory, HasTestingData, SoftDeletes;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'group_id',
        'hari',
        'id_jam',
        'id_kelas',
        'id_mapel',
        'id_guru',
        'id_ruangan',
        'id_tahun_ajaran',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];

    /**
     * Relasi ke Ruangan
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'id_ruangan');
    }

    /**
     * Relasi ke Jam Pelajaran
     */
    public function jamPelajaran(): BelongsTo
    {
        return $this->belongsTo(JamPelajaran::class, 'id_jam', 'id');
    }

    /**
     * Alias relasi ke Jam Pelajaran
     */
    public function jam(): BelongsTo
    {
        return $this->jamPelajaran();
    }

    /**
     * Relasi ke Kelas
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'id');
    }

    /**
     * Relasi ke Mata Pelajaran
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'id_mapel', 'id');
    }

    /**
     * Alias relasi ke Mata Pelajaran
     */
    public function mapel(): BelongsTo
    {
        return $this->mataPelajaran();
    }

    /**
     * Relasi ke User (Guru Pengajar)
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_guru', 'id');
    }

    /**
     * Relasi ke Tahun Ajaran
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'id_tahun_ajaran', 'id');
    }

    /**
     * Relasi ke Jurnal
     */
    public function jurnal(): HasMany
    {
        return $this->hasMany(Jurnal::class, 'id_jadwal', 'id');
    }

    /**
     * Tambahkan kolom slot jam hasil LOOKUP ke `jadwal_pelajaran.id_jam`.
     *
     * Menghasilkan 4 atribut tambahan:
     *  - jam_ke     : nomor urut slot (tetap terbaca walau master soft-deleted)
     *  - jam_mulai  : waktu mulai slot
     *  - jam_jenis  : jenis slot (kbm / istirahat / upacara / ...)
     *  - jam_valid  : 1 bila slot tertaut ke master yang MASIH AKTIF dan hari-nya
     *                 cocok; 0 bila jadwal "gantung" (master dihapus/recreate).
     *
     * Sengaja memakai SUBQUERY, bukan LEFT JOIN: tabel `jam_pelajaran` dan
     * `jadwal_pelajaran` sama-sama punya kolom `hari`, `jenis`, `jam_ke`, dan
     * `deleted_at`. LEFT JOIN akan membuat setiap `where('hari', ...)` yang
     * sudah tersebar di seluruh aplikasi menjadi ambiguous column error.
     * Subquery menambah nol ambiguitas dan tetap jalan di MySQL & SQLite.
     *
     * Subquery membaca master TANPA filter soft-delete: `jam_ke` dari slot lama
     * tetap berguna untuk auto-heal & tampilan, sedangkan `jam_valid` yang
     * menandai kondisinya.
     */
    public function scopeWithSlot($query)
    {
        $jamTable = (new JamPelajaran)->getTable();
        $kunci = fn ($kolom) => DB::table($jamTable)
            ->select($kolom)
            ->whereColumn("{$jamTable}.id", 'jadwal_pelajaran.id_jam')
            ->limit(1);

        return $query->addSelect([
            'jam_ke' => $kunci('jam_ke'),
            'jam_mulai' => $kunci('jam_mulai'),
            'jam_jenis' => $kunci('jenis'),
            'jam_valid' => $kunci(DB::raw(
                "CASE WHEN {$jamTable}.deleted_at IS NULL AND {$jamTable}.hari = jadwal_pelajaran.hari THEN 1 ELSE 0 END"
            )),
        ]);
    }

    /**
     * Urutkan jadwal secara KRONOLOGIS berdasarkan waktu mulai slot jam
     * (`jam_pelajaran.jam_mulai`), BUKAN berdasarkan `jam_ke` atau `id_jam`.
     *
     * `jam_ke` tidak bisa jadi kunci utama: slot non-KBM (Istirahat) memang
     * ber-`jam_ke` NULL, jadi mengurutkannya lebih dulu akan membuat jadwal
     * pada jam istirahat meloncat ke bawah / ke atas daftar.
     *
     * Ini urutan kanonik seluruh aplikasi: posisi slot ditentukan oleh waktu
     * mulai, sehingga jadwal tetap tampil pada baris yang benar walaupun
     * master jam dihapus-dibuat ulang dan ID-nya bergeser (mis. 91, 93, 100).
     *
     * Slot dengan jam_mulai NULL / master hilang total (jadwal gantung) tetap
     * ditampilkan, hanya digeser ke akhir daftar — dashboard & matriks tidak
     * boleh error atau kehilangan jadwal gara-gara master jam berubah.
     */
    public function scopeUrutkanSlot($query)
    {
        $jamTable = (new JamPelajaran)->getTable();
        $jamKe = "(select {$jamTable}.jam_ke from {$jamTable} where {$jamTable}.id = jadwal_pelajaran.id_jam)";
        $jamMulai = "(select {$jamTable}.jam_mulai from {$jamTable} where {$jamTable}.id = jadwal_pelajaran.id_jam)";

        return $query
            // KRONOLOGIS dulu: jam mulai slot, NULL (master hilang) geser ke akhir.
            ->orderByRaw("{$jamMulai} IS NULL, {$jamMulai} ASC")
            // Tie-breaker: nomor jam ke, lalu hari, lalu id_jam.
            ->orderByRaw($jamKe)
            ->orderBy('jadwal_pelajaran.hari')
            ->orderBy('jadwal_pelajaran.id_jam');
    }

    /**
     * Convenience: lookup slot jam + urut berdasarkan jam_ke dalam satu panggilan.
     */
    public function scopeUrutkanPerSlot($query)
    {
        return $query->withSlot()->urutkanSlot();
    }

    /**
     * Flag: apakah `id_jam` menunjuk master jam yang masih valid?
     *
     * True bila slot ditemukan, tidak soft-deleted, hari cocok, dan shift-nya
     * sesuai (slot Global / NULL selalu dianggap cocok).
     */
    public function hasValidSlot(?int $shiftId = null): bool
    {
        // Bila kolom hasil withSlot() tersedia, tidak perlu query tambahan.
        if (array_key_exists('jam_valid', $this->attributes)) {
            if ((int) $this->attributes['jam_valid'] !== 1) {
                return false;
            }

            if ($shiftId === null) {
                return true;
            }

            $shiftSlot = JamPelajaran::withoutGlobalScope(ActiveTahunAjaranScope::class)
                ->whereKey($this->id_jam)
                ->value('shift_id');

            return $shiftSlot === null || (int) $shiftSlot === (int) $shiftId;
        }

        return JamPelajaran::isSlotPlausibel($this->jamPelajaran, (string) $this->hari, $shiftId);
    }
}
