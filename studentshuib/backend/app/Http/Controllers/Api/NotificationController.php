<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/v1/student/notifications  or  /api/v1/admin/notifications
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->where('channel', 'in_app')
            ->when($request->boolean('unread_only'), fn($q) => $q->where('is_read', false))
            ->orderByDesc('created_at')
            ->paginate(30);

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('channel', 'in_app')
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    // PUT /api/v1/student/notifications/{id}/read
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Marked as read.']);
    }

    // PUT /api/v1/student/notifications/read-all
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
