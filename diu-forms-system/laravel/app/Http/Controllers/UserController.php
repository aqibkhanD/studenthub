<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * User & role management for admin accounts (super_admin only).
 *
 * Routes (under ['auth:sanctum', 'role:super_admin']):
 *   GET    /admin/users                  → index()
 *   POST   /admin/users                  → store()
 *   GET    /admin/users/{user}            → show()
 *   PUT    /admin/users/{user}            → update()
 *   POST   /admin/users/{user}/toggle     → toggleActive()
 *   POST   /admin/users/{user}/role       → changeRole()
 *   POST   /admin/users/{user}/reset-password → resetPassword()
 *   DELETE /admin/users/{user}            → destroy()
 *
 * Read-only profile (own account, any authenticated user):
 *   GET    /admin/users/me               → me()
 *   PUT    /admin/users/me               → updateMe()
 */
class UserController extends Controller
{
    // ------------------------------------------------------------------
    // User list
    // ------------------------------------------------------------------

    /**
     * GET /admin/users
     * Supports ?search=, ?role=, ?status=active|inactive, ?department_id=
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('department')
            ->where('role', '!=', 'student'); // Admin + Super Admin only

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $users = $query->orderBy('name')->paginate(30);

        return response()->json([
            'data'       => $users->map(fn($u) => $this->formatUser($u)),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Create admin user
    // ------------------------------------------------------------------

    /**
     * POST /admin/users
     * Creates an admin or super_admin account.
     * Students self-register; they cannot be created here.
     */
    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'department_id' => $data['department_id'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Admin user created successfully.',
            'data'    => $this->formatUser($user->load('department')),
        ], 201);
    }

    // ------------------------------------------------------------------
    // Show single user
    // ------------------------------------------------------------------

    /**
     * GET /admin/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->formatUser($user->load('department')),
        ]);
    }

    // ------------------------------------------------------------------
    // Update user (super_admin only)
    // ------------------------------------------------------------------

    /**
     * PUT /admin/users/{user}
     */
    public function update(StoreAdminUserRequest $request, User $user): JsonResponse
    {
        // Prevent super_admin from accidentally demoting themselves to admin
        // if they are the only super_admin.
        if (
            $user->id === $request->user()->id &&
            isset($request->validated()['role']) &&
            $request->validated()['role'] !== 'super_admin'
        ) {
            $superAdminCount = User::where('role', 'super_admin')->where('is_active', true)->count();
            if ($superAdminCount <= 1) {
                return response()->json([
                    'message' => 'Cannot demote the only active super admin.',
                ], 422);
            }
        }

        $data = $request->validated();

        $update = array_filter([
            'name'          => $data['name'] ?? null,
            'email'         => $data['email'] ?? null,
            'role'          => $data['role'] ?? null,
            'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : '__skip__',
            'phone'         => array_key_exists('phone', $data) ? $data['phone'] : '__skip__',
            'is_active'     => $data['is_active'] ?? null,
        ], fn($v) => $v !== null && $v !== '__skip__');

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        $user->update($update);

        return response()->json([
            'message' => 'User updated successfully.',
            'data'    => $this->formatUser($user->fresh('department')),
        ]);
    }

    // ------------------------------------------------------------------
    // Toggle active status
    // ------------------------------------------------------------------

    /**
     * POST /admin/users/{user}/toggle
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        // Prevent deactivating oneself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        // Prevent deactivating the last super_admin
        if ($user->role === 'super_admin' && $user->is_active) {
            $activeCount = User::where('role', 'super_admin')->where('is_active', true)->count();
            if ($activeCount <= 1) {
                return response()->json([
                    'message' => 'Cannot deactivate the only active super admin account.',
                ], 422);
            }
        }

        $user->update(['is_active' => !$user->is_active]);

        // Revoke all tokens if deactivated
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message'   => 'User ' . ($user->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $user->is_active,
        ]);
    }

    // ------------------------------------------------------------------
    // Role change
    // ------------------------------------------------------------------

    /**
     * POST /admin/users/{user}/role
     * Body: { role: 'admin' | 'super_admin' }
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:admin,super_admin'],
        ]);

        // Same guard as update() — cannot orphan super_admin role
        if ($user->role === 'super_admin' && $data['role'] === 'admin') {
            $count = User::where('role', 'super_admin')->where('is_active', true)->count();
            if ($count <= 1) {
                return response()->json([
                    'message' => 'Cannot demote the only active super admin.',
                ], 422);
            }
        }

        $user->update(['role' => $data['role']]);

        // If downgraded, revoke tokens so the next login re-issues a correctly-scoped token
        if ($data['role'] !== $user->getOriginal('role')) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => "Role changed to {$data['role']}.",
            'data'    => $this->formatUser($user->fresh('department')),
        ]);
    }

    // ------------------------------------------------------------------
    // Password reset (admin initiated)
    // ------------------------------------------------------------------

    /**
     * POST /admin/users/{user}/reset-password
     * Body: { password: 'NewPassword1' }
     *
     * Super admin sets a new password directly. A separate "send reset email"
     * feature would live in a PasswordResetController.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => [
                'required',
                \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        // Invalidate all existing tokens so the user must log in again
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. The user must log in again.',
        ]);
    }

    // ------------------------------------------------------------------
    // Delete user
    // ------------------------------------------------------------------

    /**
     * DELETE /admin/users/{user}
     *
     * Hard-deletes only if the user has never submitted forms.
     * Otherwise soft-deactivates (avoids breaking audit trail).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($user->submissions()->exists() || $user->auditLogs()->exists()) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete();

            return response()->json([
                'message'     => 'User has activity records and cannot be permanently deleted. The account has been deactivated.',
                'deactivated' => true,
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    // ------------------------------------------------------------------
    // Own profile
    // ------------------------------------------------------------------

    /**
     * GET /admin/users/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->formatUser($request->user()->load('department')),
        ]);
    }

    /**
     * PUT /admin/users/me
     * Allows any authenticated admin to update their own name, phone, and password.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:120'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'password'     => [
                'sometimes', 'nullable',
                \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers(),
            ],
            'current_password' => ['required_with:password', 'string'],
        ]);

        $user = $request->user();

        // Verify current password before allowing a change
        if (!empty($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is incorrect.'], 422);
            }
        }

        $update = array_filter([
            'name'  => $data['name'] ?? null,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : '__skip__',
        ], fn($v) => $v !== null && $v !== '__skip__');

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
            // Revoke all other tokens (keep current session active)
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
        }

        $user->update($update);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->formatUser($user->fresh('department')),
        ]);
    }

    // ------------------------------------------------------------------
    // Formatting helper
    // ------------------------------------------------------------------

    private function formatUser(User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'role'            => $user->role,
            'role_label'      => match ($user->role) {
                'super_admin' => 'Super Admin',
                'admin'       => 'Admin',
                'student'     => 'Student',
                default       => ucfirst($user->role),
            },
            'department_id'   => $user->department_id,
            'department_name' => $user->department?->name,
            'phone'           => $user->phone,
            'student_id'      => $user->student_id ?? null,
            'is_active'       => (bool) $user->is_active,
            'email_verified'  => !is_null($user->email_verified_at),
            'last_login_at'   => $user->last_login_at?->toIso8601String(),
            'created_at'      => $user->created_at->toIso8601String(),
        ];
    }
}
