<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaRutin extends Model
{
    use HasFactory;

    protected $table = 'agenda_rutin';

    protected $fillable = [
        'hari',
        'jam_ke',
        'nama_agenda',
        'is_active',
    ];

    protected $casts = [
        'jam_ke'    => 'integer',
        'is_active' => 'boolean',
    ];
}
