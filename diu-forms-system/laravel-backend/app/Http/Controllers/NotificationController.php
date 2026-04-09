<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/student/notifications
     */
    public function studentIndex(Request $request): JsonResponse
    {
        $notifications = InAppNotification::where('notifiable_id',   $request->user()->id)
            ->where('notifiable_type', User::class)
            ->latest()
            ->paginate(30);

        return response()->json($notifications);
    }

    /**
     * GET /api/admin/notifications
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $notifications = InAppNotification::where('notifiable_id',   $request->user()->id)
            ->where('notifiable_type', Admin::class)
            ->latest()
            ->paginate(30);

        return response()->json($notifications);
    }

    /**
     * POST /api/{student|admin}/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = InAppNotification::where('id', $id)
            ->where('notifiable_id',   $request->user()->id)
            ->firstOrFail();

        $notif->markRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * POST /api/{student|admin}/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $modelClass = $request->user() instanceof Admin ? Admin::class : User::class;

        InAppNotification::where('notifiable_id',   $request->user()->id)
            ->where('notifiable_type', $modelClass)
            ->where('read', false)
            ->update(['read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * GET /api/unsubscribe?token=xxx&channel=email
     * One-click unsubscribe from email links — no auth required.
     */
    public function handleUnsubscribe(Request $request): \Illuminate\Http\Response
    {
        $token   = $request->query('token');
        $channel = $request->query('channel', 'email');

        $user = User::where('unsubscribe_token', $token)->first()
             ?? Admin::where('unsubscribe_token', $token)->first();

        if (!$user) {
            return response('Invalid or expired unsubscribe link.', 404);
        }

        $field = $channel === 'sms' ? 'notif_sms_enabled' : 'notif_email_enabled';
        $user->update([$field => false]);

        return response(
            '<html><body style="font-family:sans-serif;text-align:center;padding:60px">'
            . '<h2>Unsubscribed</h2>'
            . '<p>You have been unsubscribed from ' . htmlspecialchars($channel) . ' notifications.</p>'
            . '<p>You can re-enable them at any time in your notification settings.</p>'
            . '</body></html>',
            200
        );
    }
}
