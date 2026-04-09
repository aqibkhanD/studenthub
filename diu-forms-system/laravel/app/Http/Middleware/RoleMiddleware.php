<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Usage in routes: middleware('role:admin,super_admin')
     * Comma-separated list of allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
