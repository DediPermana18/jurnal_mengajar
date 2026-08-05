<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guru extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'guru';
    protected $primaryKey = 'id_guru';
    public $timestamps = false; // Gunakan false jika tidak ada created_at/updated_at

    protected $fillable = ['nip', 'nama_guru', 'no_hp'];

    public function kelasWali()
    {
        return $this->hasMany(Kelas::class, 'id_guru_wali', 'id_guru');
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class, 'id_guru', 'id_guru');
    }
}