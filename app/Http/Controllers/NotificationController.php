<?php
namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, string $id, NotificationService $notifications)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        // Normalize legacy absolute links too, so notifications created before
        // v1.0.23 cannot switch host/scheme and accidentally lose the session.
        $destination = $notifications->inAppPath($notification->data['url'] ?? null);

        return $destination ? redirect()->to($destination) : back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi sudah dibaca.');
    }
}
