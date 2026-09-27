<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Percobaan login dari perangkat lain saat akun sedang aktif digunakan.
 *
 * Disiarkan ke kanal privat `user.{id}.security` (Pusher/Reverb bila dikonfigurasi);
 * frontend Device A menampilkan toast peringatan keamanan real-time.
 * Saat tidak ada broadcaster (BROADCAST_CONNECTION=null), peringatan tetap
 * tersedia via polling heartbeat (last_security_alert_at).
 */
class ConcurrentLoginAttempt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $attempted_at;

    public function __construct(
        public User $user,
        public string $message = 'Terdeteksi percobaan login ke akun Anda dari perangkat lain.',
        public ?string $ip = null,
        public ?string $user_agent = null,
        ?string $attempted_at = null,
    ) {
        $this->attempted_at = $attempted_at ?? now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->user->id.'.security')];
    }
}