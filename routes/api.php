<?php

use App\Http\Middleware\ResolveTimezone;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file is loaded by bootstrap/app.php via the `withRouting(api: ...)`
| call. All routes declared here are auto-prefixed with `api` and run
| under the `api` middleware group. The group below gates the public
| surface behind auth:sanctum (so the 401 envelope is rendered for
| every authenticated endpoint) and runs ResolveTimezone BEFORE
| auth:sanctum so the 401 response is also rendered in the requested
| TZ (design risk N7).
|
*/

Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->prefix('api')->group(function (): void {
    // PR 3 / PR 2 add the 16 public + mutation endpoints here.
    // PR 1 lands a minimal /api/me placeholder so the auth surface is
    // testable end-to-end (see AuthSanctumTest in T-API-7).
});

