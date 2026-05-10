<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Override the default Authenticate middleware so that unauthenticated API
 * requests never try to redirect to a named 'login' route (which doesn't exist
 * in this API-only application).  Returning null here causes the parent class
 * to throw AuthenticationException cleanly, which the exception handler in
 * bootstrap/app.php converts to a JSON 401 response.
 */
class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
