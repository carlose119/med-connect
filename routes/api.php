<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentTransitionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\MedicalAttachmentController;
use App\Http\Controllers\Api\MedicalHistoryController;
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
Route::middleware([ResolveTimezone::class])->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware([ResolveTimezone::class, 'auth:sanctum'])->group(function (): void {
    // PR 4 — agenda-http — Auth surface (me + logout). The canonical
    // current-user path is /api/auth/me (was /api/me in PR 1 + PR 3;
    // the /api/me route + MeController were retired in T-API-46).
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // PR 2 — agenda-http — Mutations.
    // The 16 read endpoints land in PR 3.
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'cancel']);

    // PR 3 — agenda-http — State transitions.
    Route::post('/appointments/{appointment}/transitions/confirm', [AppointmentTransitionController::class, 'confirm']);
    Route::post('/appointments/{appointment}/transitions/complete', [AppointmentTransitionController::class, 'complete']);
    Route::post('/appointments/{appointment}/transitions/no-show', [AppointmentTransitionController::class, 'markNoShow']);

    // PR 3 — agenda-http — Doctor directory.
    Route::get('/doctors', [DoctorController::class, 'index']);
    Route::get('/doctors/{doctor}', [DoctorController::class, 'show']);
    Route::get('/doctors/{doctor}/slots', [DoctorController::class, 'slots']);
    Route::get('/specialties', [SpecialtyController::class, 'index']);
    Route::get('/patients/{patient}', [PatientController::class, 'show']);
    Route::get('/medical-histories/{medical_history}', [MedicalHistoryController::class, 'show']);
    Route::get('/medical-histories/{medical_history}/notes', [MedicalNoteController::class, 'index']);
    Route::post('/medical-histories/{medical_history}/notes', [MedicalNoteController::class, 'store']);
    Route::get('/medical-notes/{medical_note}', [MedicalNoteController::class, 'show']);
    Route::post('/medical-notes/{medical_note}/amend', [MedicalNoteController::class, 'amend']);
    Route::post('/medical-notes/{medical_note}/attachments', [MedicalAttachmentController::class, 'upload']);
    Route::get('/medical-notes/{medical_note}/attachments', [MedicalAttachmentController::class, 'index']);
    Route::delete('/medical-attachments/{medical_attachment}', [MedicalAttachmentController::class, 'destroy']);
    Route::get('/prescriptions', [PrescriptionController::class, 'index']);
    Route::post('/prescriptions', [PrescriptionController::class, 'store']);
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
    Route::put('/prescriptions/{prescription}', [PrescriptionController::class, 'update']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
