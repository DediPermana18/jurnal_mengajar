<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JurnalMengajar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jurnal_mengajar';
    protected $primaryKey = 'id_jurnal';
    public $timestamps = false;

    protected $fillable = [
        'id_jadwal',
        'tanggal',
        'materi',
        'keterangan',
        'status_guru',
        'jumlah_siswa_hadir',
        'is_ttd',
        'semester',
        'tahun_ajaran',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'id_jadwal');
    }

    public function guru()
    {
        return $this->hasOneThrough(
            Guru::class,
            Jadwal::class,
            'id_jadwal', // FK on Jadwal
            'id_guru',   // FK on Guru
            'id_jadwal', // Local key on JurnalMengajar
            'id_guru'    // Local key on Jadwal
        );
    }

    public function mapel()
    {
        return $this->hasOneThrough(
            Mapel::class,
            Jadwal::class,
            'id_jadwal',
            'id_mapel',
            'id_jadwal',
            'id_mapel'
        );
    }

    public function kelas()
    {
        return $this->hasOneThrough(
            Kelas::class,
            Jadwal::class,
            'id_jadwal',
            'id_kelas',
            'id_jadwal',
            'id_kelas'
        );
    }

    public function absensiSiswa()
    {
        return $this->hasMany(AbsensiSiswa::class, 'id_jurnal', 'id_jurnal');
    }
}