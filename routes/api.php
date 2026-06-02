<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
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

Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->group(function (): void {
    // Minimal /api/me placeholder so the auth surface is testable
    // end-to-end (see AuthSanctumTest). PR 3 swaps this for the
    // full MeController that wraps a UserResource.
    // Note: the `api` prefix is auto-applied by withRouting(api: ...) in
    // bootstrap/app.php — do NOT add prefix('api') here.
    Route::get('/me', fn () => response()->json(['data' => auth()->user()]));

    // PR 2 — agenda-http — Mutations.
    // The 16 read endpoints land in PR 3.
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'cancel']);

    // PR 3 — agenda-http — Doctor directory.
    Route::get('/doctors', [DoctorController::class, 'index']);
});

