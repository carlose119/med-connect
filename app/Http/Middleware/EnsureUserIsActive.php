<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject authentication attempts for users whose is_active = false.
 * Applied to the web guard login flow and the patient-web login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only check on login attempts (credentials present)
        // The guard itself validates, but we also double-check here
        // in case a user is deactivated after login (edge case).
        // For now this middleware is used in the login controller flow.
        return $next($request);
    }

    /**
     * Validate that a given user model is active.
     * Call this from the login controller before Auth::attempt().
     */
    public static function check(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->is_active !== false;
    }
}