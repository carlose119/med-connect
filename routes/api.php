<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file is loaded by bootstrap/app.php via the `withRouting(api: ...)`
| call. All routes declared here are auto-prefixed with `api` and run
| under the `api` middleware group. PR 1 (agenda-http) adds the
| `auth:sanctum` + `ResolveTimezone` middleware inside the group below;
| the route stubs land in subsequent tasks (T-API-6 / T-API-7).
|
*/

Route::middleware([])->prefix('api')->group(function (): void {
    // Placeholder — the real route group (with auth:sanctum + ResolveTimezone)
    // lands in T-API-6 and T-API-7. This file must exist and be registered
    // before T-API-5's ExceptionMappingTest can mount its throw route.
});
