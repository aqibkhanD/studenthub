<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage in routes: middleware('role:admin,super_admin')
     * Passes if the authenticated user's role matches ANY of the given roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
                'required_role' => $roles,
                'your_role'     => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
