<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Permintaan persetujuan login interaktif yang dikirim ke perangkat aktif (Device A).
 *
 * Dipicu saat Device B mencoba login ke akun yang sedang AKTIF di Device A.
 * Login Device B TIDAK dituntaskan — alih-alih dibuat status pending
 * (LoginApprovalService, TTL 60 detik) dan perangkat aktif diberi kesempatan
 * untuk menekan [Izinkan] / [Tolak] lewat modal keamanan real-time.
 *
 * Disiarkan ke kanal privat `user.{id}.security`; frontend Device A
 * menampilkan modal interaktif. Saat tidak ada broadcaster
 * (BROADCAST_CONNECTION=null), modal tetap muncul via polling fallback
 * ke endpoint pending approval.
 */
class LoginApprovalRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $requested_at;

    public function __construct(
        public User $user,
        public string $request_id,
        public array $device_info,
        public string $message = 'Ada perangkat lain mencoba login ke akun Anda.',
        ?string $requested_at = null,
    ) {
        $this->requested_at = $requested_at ?? now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->user->id.'.security')];
    }
}