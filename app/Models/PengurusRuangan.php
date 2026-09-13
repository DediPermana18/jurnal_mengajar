<?php

namespace App\Models;

use App\Models\Concerns\HasTestingData;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PengurusRuangan extends Pivot
{
    use HasTestingData;

    protected $table = 'pengurus_ruangan';

    protected $fillable = [
        'ruangan_id',
        'user_id',
        'is_testing_data',
    ];

    protected $casts = [
        'is_testing_data' => 'boolean',
    ];
}
