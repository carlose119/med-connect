<?php

namespace App\Http\Middleware;

use App\Clinic\Timezone;
use App\Exceptions\InvalidTimezoneException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the per-request timezone and stores it on the request's
 * attribute bag under the 'tz' key. Downstream code (API Resources,
 * datetime-accepting FormRequests, the slot service, the error
 * envelope) reads the resolved instance from there.
 *
 * Resolution chain (per agenda-http/design.md §7):
 *   1. `?tz=` query param — validated via Timezone::isValid()
 *      Invalid → throws InvalidTimezoneException (422 INVALID_TIMEZONE).
 *   2. Falls back to `config('clinic.timezone')`
 *      (default `America/Argentina/Buenos_Aires`).
 *
 * MUST run before `auth:sanctum` so the 401 response is also rendered
 * in the requested TZ (design risk N7). Mounted as
 * `Route::middleware([ResolveTimezone::class, 'auth:sanctum'])`
 * in routes/api.php.
 */
class ResolveTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = (string) $request->query('tz', '');

        if ($requested === '') {
            $name = (string) config('clinic.timezone', 'America/Argentina/Buenos_Aires');
        } else {
            if (! Timezone::isValid($requested)) {
                throw new InvalidTimezoneException($requested);
            }
            $name = $requested;
        }

        $request->attributes->set('tz', new Timezone($name));

        return $next($request);
    }
}
