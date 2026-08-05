<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbsensiSiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'absensi_siswa';
    protected $primaryKey = 'id_absensi';
    public $timestamps = false;

    protected $fillable = ['id_jurnal', 'id_siswa', 'status'];

    public function jurnal()
    {
        return $this->belongsTo(JurnalMengajar::class, 'id_jurnal', 'id_jurnal');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'id_siswa');
    }
}