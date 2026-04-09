<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminManagementController extends Controller
{
    // ── Dashboard stats ────────────────────────────────────────────

    /**
     * GET /api/admin/dashboard/stats
     * Returns counters for the admin dashboard summary cards.
     * Super admins see all; regular admins see their department only.
     */
    public function stats(Request $request): JsonResponse
    {
        $admin = $request->user();
        $query = Submission::query();

        if (!$admin->isSuperAdmin()) {
            $query->where('department_id', $admin->department_id);
        }

        $total      = (clone $query)->count();
        $pending    = (clone $query)->whereIn('status', ['submitted', 'routed'])->count();
        $inReview   = (clone $query)->where('status', 'in_review')->count();
        $completed  = (clone $query)->whereIn('status', ['approved', 'completed'])->count();
        $rejected   = (clone $query)->where('status', 'rejected')->count();
        $slaBreached = (clone $query)
            ->whereNotIn('status', Submission::TERMINAL_STATUSES)
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->count();
        $slaWarning = (clone $query)
            ->whereNotIn('status', Submission::TERMINAL_STATUSES)
            ->whereNotNull('sla_deadline')
            ->whereBetween('sla_deadline', [now(), now()->addHours(4)])
            ->count();

        // Recent submissions (last 10)
        $recent = (clone $query)
            ->with(['user:id,name,student_id', 'formType:id,name,category'])
            ->latest('submitted_at')
            ->limit(10)
            ->get(['id', 'ref', 'status', 'submitted_at', 'sla_deadline', 'user_id', 'form_type_id', 'department_id']);

        return response()->json([
            'counts' => [
                'total'       => $total,
                'pending'     => $pending,
                'in_review'   => $inReview,
                'completed'   => $completed,
                'rejected'    => $rejected,
                'sla_breached'=> $slaBreached,
                'sla_warning' => $slaWarning,
            ],
            'recent' => $recent,
        ]);
    }

    // ── Admin user management (super_admin only) ───────────────────

    /**
     * GET /api/admin/users
     */
    public function listAdmins(Request $request): JsonResponse
    {
        $query = Admin::with('department:id,name,code')->withTrashed();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            if ($status === 'active')   $query->whereNull('deleted_at')->where('is_active', true);
            if ($status === 'inactive') $query->where('is_active', false);
        }

        return response()->json($query->paginate(25));
    }

    /**
     * POST /api/admin/users
     */
    public function createAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:120',
            'email'         => 'required|email|unique:admins,email',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'required|in:admin,super_admin',
            'department_id' => 'nullable|exists:departments,id',
            'password'      => 'required|string|min:8',
        ]);

        $admin = Admin::create([
            ...$validated,
            'password'          => Hash::make($validated['password']),
            'unsubscribe_token' => Str::random(64),
        ]);

        AuditLog::record(
            $request->user(), 'role_change',
            "Created admin account for {$admin->name} with role {$admin->role}",
            null,
            ['field' => 'role', 'from' => null, 'to' => $admin->role]
        );

        // Notify the new admin
        app(\App\Services\NotificationService::class)->dispatch(
            $request->user(), 'new_admin',
            ['notif_title' => 'New admin created', 'notif_body' => "{$admin->name} added as {$admin->role}"]
        );

        return response()->json($admin->load('department'), 201);
    }

    /**
     * PATCH /api/admin/users/{id}
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:120',
            'phone'         => 'sometimes|nullable|string|max:20',
            'role'          => 'sometimes|in:admin,super_admin',
            'department_id' => 'sometimes|nullable|exists:departments,id',
        ]);

        $oldRole = $admin->role;
        $admin->update($validated);

        if (isset($validated['role']) && $validated['role'] !== $oldRole) {
            AuditLog::record(
                $request->user(), 'role_change',
                "Changed {$admin->name}'s role from {$oldRole} to {$validated['role']}",
                null,
                ['field' => 'role', 'from' => $oldRole, 'to' => $validated['role']]
            );
        } else {
            AuditLog::record(
                $request->user(), 'setting_change',
                "Updated admin profile for {$admin->name}"
            );
        }

        return response()->json($admin->fresh()->load('department'));
    }

    /**
     * PATCH /api/admin/users/{id}/toggle
     * Activate or deactivate an admin account.
     */
    public function toggleAdmin(Request $request, int $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);
        $admin->update(['is_active' => !$admin->is_active]);

        $action = $admin->is_active ? 'activated' : 'deactivated';

        AuditLog::record(
            $request->user(), 'setting_change',
            "Admin account for {$admin->name} {$action}"
        );

        return response()->json(['is_active' => $admin->is_active]);
    }

    /**
     * POST /api/admin/users/{id}/reset-pw
     * Generate a temporary password and (in production) email it to the admin.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $admin    = Admin::findOrFail($id);
        $tempPass = Str::random(12);

        $admin->update(['password' => Hash::make($tempPass)]);

        AuditLog::record(
            $request->user(), 'setting_change',
            "Password reset for admin {$admin->name}"
        );

        // In production: Mail::to($admin->email)->send(new PasswordResetMail($admin, $tempPass));
        // Returning plain-text only in dev — never do this in production
        return response()->json([
            'message'       => 'Password reset. Send the temporary password to the admin securely.',
            'temp_password' => app()->isLocal() ? $tempPass : '[hidden in production]',
        ]);
    }

    /**
     * PATCH /api/admin/me/notifications  (also used by AdminAuthController)
     * Update the authenticated admin's own notification preferences.
     */
    public function updateNotifPrefs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'               => 'sometimes|string|max:20',
            'notif_email_enabled' => 'sometimes|boolean',
            'notif_sms_enabled'   => 'sometimes|boolean',
            'quiet_hours_enabled' => 'sometimes|boolean',
            'quiet_start'         => 'sometimes|date_format:H:i',
            'quiet_end'           => 'sometimes|date_format:H:i',
            'preferences'         => 'sometimes|array',
            'preferences.*.event_type' => 'required_with:preferences|string',
            'preferences.*.channel'    => 'required_with:preferences|in:email,sms,inapp',
            'preferences.*.delivery'   => 'required_with:preferences|in:immediate,digest_hourly,digest_daily,never',
        ]);

        $admin = $request->user();
        $admin->update(collect($validated)->except('preferences')->toArray());

        foreach ($validated['preferences'] ?? [] as $pref) {
            \App\Models\NotificationPreference::set($admin, $pref['event_type'], $pref['channel'], $pref['delivery']);
        }

        return response()->json(['message' => 'Preferences updated.']);
    }
}
