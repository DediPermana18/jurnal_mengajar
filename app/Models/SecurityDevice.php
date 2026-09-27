<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nama kustom perangkat yang diberi oleh pemilik akun.
 *
 * Metadata tampilan (bukan audit): tabel ini BOLEH di-update/dihapus oleh
 * pemiliknya. Immutability security_logs tidak terpengaruh — nama tersimpan
 * di sini per (user_id, fingerprint) dan berlaku untuk seluruh baris log
 * dengan fingerprint yang sama.
 */
class SecurityDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}