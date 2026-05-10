<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api:        __DIR__ . '/../routes/api.php',
        commands:   __DIR__ . '/../routes/console.php',
        health:     '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Override the default Authenticate middleware with our API-safe version.
        // The default calls route('login') when unauthenticated — that route doesn't
        // exist in a pure bearer-token API, causing a RouteNotFoundException (500).
        // Our custom class returns null from redirectTo() instead, so the framework
        // throws AuthenticationException cleanly → our handler returns JSON 401.
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // CORS is handled globally via HandleCors (already in the default API stack).
        // Do NOT call statefulApi() — that is for SPA cookie auth, not bearer tokens.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for all API errors
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors'  => $e->errors(),
                    ], 422);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
                    return response()->json(['message' => 'Unauthenticated.'], 401);
                }

                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json(['message' => 'Resource not found.'], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json(['message' => 'Endpoint not found.'], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                    return response()->json(['message' => 'Method not allowed.'], 405);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }

                // Unhandled exceptions — hide details in production
                if (!config('app.debug')) {
                    return response()->json(['message' => 'Server error. Please try again later.'], 500);
                }
            }
        });
    })->create();
