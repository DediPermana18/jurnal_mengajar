<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaRutin extends Model
{
    use HasFactory, HasTestingData;

    protected $table = 'agenda_rutin';

    protected $fillable = [
        'hari',
        'jam_ke',
        'nama_agenda',
        'is_active',
    ];

    protected $casts = [
        'jam_ke' => 'integer',
        'is_active' => 'boolean',
        'is_testing' => 'boolean',
    ];
}
