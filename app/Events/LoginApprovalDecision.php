<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Keputusan Device A atas permintaan login Device B (approved | rejected).
 *
 * Disiarkan ke kanal privat `user.{id}.security` agar frontend perangkat aktif
 * dapat menutup modal interaktif & menampilkan status. Device B menerima
 * keputusan lewat polling status (ia belum terautentikasi → tidak bisa
 * mendengarkan kanal privat).
 */
class LoginApprovalDecision implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $resolved_at;

    public function __construct(
        public User $user,
        public string $request_id,
        public string $status, // 'approved' | 'rejected'
        ?string $resolved_at = null,
    ) {
        $this->resolved_at = $resolved_at ?? now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->user->id.'.security')];
    }
}