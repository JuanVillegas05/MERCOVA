<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = UserNotification::query()->where('user_id', $request->user()->id);

        if ($request->boolean('unread')) {
            $query->unread();
        }

        return response()->json([
            'data' => $query->latest()->paginate(20),
            'unread_count' => UserNotification::where('user_id', $request->user()->id)->unread()->count(),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'Esta notificación no te pertenece.');
        }

        $notification->markAsRead();

        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Todas las notificaciones fueron marcadas como leídas.',
        ]);
    }
}
