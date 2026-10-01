<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi untuk user yang sedang login (terbaru dulu).
     * Notifikasi difilter berdasarkan role/jabatan user (misal: Waka Kesiswaan tidak melihat notifikasi izin guru).
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $notifications = $user
            ->scopedNotifications()
            ->latest()
            ->limit(50)
            ->get();

        $unreadCount = $user
            ->scopedUnreadNotifications()
            ->count();

        if (request()->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => data_get($n->data, 'title', 'Notifikasi'),
                    'message' => data_get($n->data, 'message', ''),
                    'category' => data_get($n->data, 'category', ''),
                    'url' => data_get($n->data, 'url', '#'),
                    'read_at' => $n->read_at?->diffForHumans(),
                    'is_read' => $n->read_at !== null,
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
        $user = Auth::user();
        $notification = $user
            ?->scopedNotifications()
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
        $user = Auth::user();
        if ($user) {
            $user->scopedUnreadNotifications()->get()->markAsRead();
        }

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
        $user = Auth::user();
        $count = $user ? $user->scopedUnreadNotifications()->count() : 0;

        return response()->json(['unread_count' => $count]);
    }
}
