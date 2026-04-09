<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * POST /api/admin/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$admin->is_active || $admin->trashed()) {
            return response()->json(['message' => 'This account has been deactivated.'], 403);
        }

        $admin->tokens()->where('name', 'admin-session')->delete();

        // Abilities scoped to role so middleware can distinguish admin vs super_admin
        $abilities = [$admin->role]; // ['admin'] or ['super_admin']

        $token = $admin->createToken('admin-session', $abilities)->plainTextToken;

        AuditLog::record($admin, 'login', "Admin logged in from {$request->ip()}");

        return response()->json([
            'token' => $token,
            'admin' => [
                'id'            => $admin->id,
                'name'          => $admin->name,
                'email'         => $admin->email,
                'role'          => $admin->role,
                'department_id' => $admin->department_id,
                'department'    => $admin->department?->name,
            ],
        ]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/admin/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'admin'        => $request->user()->load('department'),
            'unread_count' => $request->user()->inAppNotifications()->where('read', false)->count(),
        ]);
    }
}
