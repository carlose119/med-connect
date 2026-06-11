<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentTransitionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MedicalAttachmentController;
use App\Http\Controllers\Api\MedicalHistoryController;
use App\Http\Controllers\Api\PatientMedicalHistoryController;
use App\Http\Controllers\Api\MedicalNoteController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
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

// PR 4 — agenda-http — Public auth surface (login only). Runs
// ResolveTimezone so the 422 INVALID_TIMEZONE envelope is also
// rendered for an invalid ?tz= on the login route (N7), but NOT
// auth:sanctum (login is the credential exchange, not a bearer
// request). The 401 UNAUTHENTICATED envelope for bad creds is
// handled by the PR 1 exception handler when AuthController@login
// throws AuthenticationException.
// PRD Section 6 — All patient-facing endpoints use /api/v1/ prefix for
// versioned API compliance. The unversioned /api/ aliases remain for
// backward compatibility with existing clients (mobile-auth SDD).
Route::middleware([ResolveTimezone::class])->group(function (): void {
    // v1 — public auth
    Route::prefix('v1')->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    });

    // legacy — kept for backward compat
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
});

Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->group(function (): void {
    // v1 — auth surface
    Route::prefix('v1')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // v1 — appointments
    Route::prefix('v1')->group(function (): void {
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'cancel']);
    });

    // v1 — state transitions
    Route::prefix('v1')->group(function (): void {
        Route::post('/appointments/{appointment}/transitions/confirm', [AppointmentTransitionController::class, 'confirm']);
        Route::post('/appointments/{appointment}/transitions/complete', [AppointmentTransitionController::class, 'complete']);
        Route::post('/appointments/{appointment}/transitions/no-show', [AppointmentTransitionController::class, 'markNoShow']);
    });

    // v1 — doctor directory + availability (PRD RF-2.1, RF-2.2)
    Route::prefix('v1')->group(function (): void {
        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);
        Route::get('/doctors/{doctor}/availability', [DoctorController::class, 'slots']);
        Route::get('/specialties', [SpecialtyController::class, 'index']);
    });

    // v1 — patient profile
    Route::prefix('v1')->group(function (): void {
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
    });

    // v1 — medical history (PRD RF-3.1, RF-3.2, RF-3.4)
    // Uses PatientMedicalHistoryController for auto-discovery from auth user.
    // Legacy {medical_history} ID-based routes remain under /api/ for backward compat.
    Route::prefix('v1')->group(function (): void {
        Route::get('/medical-history', [PatientMedicalHistoryController::class, 'show']);
        Route::get('/medical-history/notes', [PatientMedicalHistoryController::class, 'notes']);
    });

    // v1 — prescriptions (PRD RF-4.3)
    Route::prefix('v1')->group(function (): void {
        Route::get('/prescriptions', [PrescriptionController::class, 'index']);
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
        Route::get('/prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf']);
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
