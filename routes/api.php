<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MedicalHistoryController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\SpecialtyController;
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
    // PR 3 — agenda-http — /me. Replaces the PR 1 placeholder
    // (auth()->user() direct serialization) with a typed controller
    // + UserResource. The middleware group still gates the route.
    Route::get('/me', [MeController::class, 'show']);

    // PR 2 — agenda-http — Mutations.
    // The 16 read endpoints land in PR 3.
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'cancel']);

    // PR 3 — agenda-http — Doctor directory.
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{doctor}/slots', [DoctorController::class, 'slots']);
    Route::get('/specialties', [SpecialtyController::class, 'index']);
    Route::get('/patients/{patient}', [PatientController::class, 'show']);
    Route::get('/medical-histories/{medical_history}', [MedicalHistoryController::class, 'show']);
});

