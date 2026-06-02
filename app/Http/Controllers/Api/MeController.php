<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the "current user" endpoint.
 *
 * `show()`  → GET /api/me
 *
 * PR 1 shipped a placeholder closure at this path that returned
 * `auth()->user()` directly. PR 3 replaces it with a typed
 * controller + a `UserResource` that pins the wire shape (no
 * password, no remember_token, no email_verified_at, no
 * timestamps). The middleware group
 * `Route::middleware([ResolveTimezone::class, 'auth:sanctum'])`
 * still gates the route, so an unauthenticated request returns
 * 401 UNAUTHENTICATED via the PR 1 exception handler.
 */
class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
