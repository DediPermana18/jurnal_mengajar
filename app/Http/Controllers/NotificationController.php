<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi untuk user yang sedang login (terbaru dulu).
     */
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get();

        $unreadCount = Auth::user()
            ->unreadNotifications()
            ->count();

        if (request()->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->map(fn ($n) => [
                    'id'         => $n->id,
                    'title'      => data_get($n->data, 'title', 'Notifikasi'),
                    'message'    => data_get($n->data, 'message', ''),
                    'category'   => data_get($n->data, 'category', ''),
                    'url'        => data_get($n->data, 'url', '#'),
                    'read_at'    => $n->read_at?->diffForHumans(),
                    'is_read'    => $n->read_at !== null,
                    'created_at' => $n->created_at?->diffForHumans(),
                ]),
                'unread_count' => $unreadCount,
            ]);
        }

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, $id)
    {
        $notification = Auth::user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification && $notification->read_at === null) {
            $notification->markAsRead();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * Tandai SEMUA notifikasi sebagai sudah dibaca.
     */
    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'unread_count' => 0]);
        }

        return back();
    }

    /**
     * Jumlah notifikasi belum dibaca (dipakai polling badge).
     */
    public function unreadCount()
    {
        $count = Auth::user()->unreadNotifications()->count();

        return response()->json(['unread_count' => $count]);
    }
}
