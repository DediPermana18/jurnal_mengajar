<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak digital login (audit trail) — tabel IMMUTABLE / append-only.
 *
 * KEBIJAKAN: tabel ini TIDAK BOLEH memiliki operasi UPDATE maupun DELETE di
 * controller manapun. Baris hanya ditambahkan (SecurityAuditService::recordLogin)
 * dan dibaca (halaman Perangkat & Keamanan). Pemutusan sesi perangkat dilakukan
 * melalui tabel `sessions` + perpindahan ikatan `current_session_id`, TANPA
 * menyentuh baris security_logs ini.
 */
class SecurityLog extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'description',
        'device_name',
        'device_fingerprint',
        'device_meta',
        'is_unknown_device',
        'login_at',
        'is_current_session',
        'session_lock',
        'session_id',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'is_current_session' => 'boolean',
        'device_meta' => 'array',
        'is_unknown_device' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}