<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access control middleware.
 *
 * Usage in routes:
 *   Route::middleware('auth:api', 'role:administrator')->...
 *   Route::middleware('auth:api', 'role:engineer,administrator')->...
 *
 * Reads the role from the authenticated user's `role` column (Postgres
 * ENUM `user_role`). Multiple roles can be passed comma-separated.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message'          => 'Forbidden. Required role(s): '.implode(', ', $roles),
                'your_role'        => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
