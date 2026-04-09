<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces role-based access on API routes.
 *
 * Usage in routes/api.php:
 *   Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(...)
 *   Route::middleware(['auth:sanctum', 'role:super_admin'])->group(...)
 *   Route::middleware(['auth:sanctum', 'role:student'])->group(...)
 *
 * The authenticated user is resolved from the Sanctum token.
 * Admin tokens carry an 'admin' ability; student tokens carry a 'student' ability.
 * Role is stored on the model itself for admins (role column) and is implicit for students.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userRole = $this->resolveRole($user);

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
                'required_roles' => $roles,
                'your_role'      => $userRole,
            ], 403);
        }

        return $next($request);
    }

    private function resolveRole(mixed $user): string
    {
        // Admin model has an explicit role column
        if ($user instanceof \App\Models\Admin) {
            return $user->role; // 'admin' or 'super_admin'
        }

        // User (student) model
        return 'student';
    }
}
