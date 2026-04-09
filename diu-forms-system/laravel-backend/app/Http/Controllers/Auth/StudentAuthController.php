<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StudentAuthController extends Controller
{
    /**
     * POST /api/student/login
     * Accepts student_id OR email + password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',   // student_id or email
            'password' => 'required|string',
        ]);

        $user = User::where('student_id', $request->login)
                    ->orWhere('email', $request->login)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->trashed()) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        // Revoke previous tokens from this device (optional: keep last N)
        $user->tokens()->where('name', 'student-session')->delete();

        $token = $user->createToken('student-session', ['student'])->plainTextToken;

        AuditLog::record($user, 'login', "Student logged in from {$request->ip()}");

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'student_id' => $user->student_id,
                'name'       => $user->name,
                'email'      => $user->email,
                'department' => $user->department,
                'batch'      => $user->batch,
            ],
        ]);
    }

    /**
     * POST /api/student/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/student/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('inAppNotifications');
        return response()->json([
            'user'             => $user,
            'unread_count'     => $user->unreadNotificationCount(),
        ]);
    }

    /**
     * PATCH /api/student/me/notifications
     * Update the student's own notification preferences.
     */
    public function updateNotifPrefs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'                => 'sometimes|string|max:20',
            'notif_email_enabled'  => 'sometimes|boolean',
            'notif_sms_enabled'    => 'sometimes|boolean',
            'quiet_hours_enabled'  => 'sometimes|boolean',
            'quiet_start'          => 'sometimes|date_format:H:i',
            'quiet_end'            => 'sometimes|date_format:H:i',
            'preferences'          => 'sometimes|array',
            'preferences.*.event_type' => 'required_with:preferences|string',
            'preferences.*.channel'    => 'required_with:preferences|in:email,sms,inapp',
            'preferences.*.delivery'   => 'required_with:preferences|in:immediate,digest_hourly,digest_daily,never',
        ]);

        $user = $request->user();

        // Update channel toggles and quiet hours
        $user->update(collect($validated)->except('preferences')->toArray());

        // Upsert per-event preferences
        foreach ($validated['preferences'] ?? [] as $pref) {
            \App\Models\NotificationPreference::set($user, $pref['event_type'], $pref['channel'], $pref['delivery']);
        }

        return response()->json(['message' => 'Preferences updated.']);
    }
}
