<?php

use App\Http\Controllers\Doctor\ConsultationController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    //return view('welcome');
    return redirect()->route('patient.login');
});

Route::get('/invitation/{token}', [InvitationController::class, 'show'])
    ->middleware('guest')
    ->name('invitation.show');

Route::post('/invitation/{token}', [InvitationController::class, 'activate'])
    ->middleware('guest')
    ->name('invitation.activate');

// Doctor consultation standalone route
Route::get('/doctor/consultation/{patient_id}', [ConsultationController::class, 'show'])
    ->middleware(['auth'])
    ->name('doctor.consultation');

Route::post('/doctor/consultation/{patient_id}/save-note', [ConsultationController::class, 'saveNote'])
    ->middleware(['auth'])
    ->name('doctor.consultation.save-note');

Route::post('/doctor/consultation/{patient_id}/confirm', [ConsultationController::class, 'confirmAppointment'])
    ->middleware(['auth'])
    ->name('doctor.consultation.confirm');

Route::post('/doctor/consultation/{patient_id}/complete', [ConsultationController::class, 'completeConsultation'])
    ->middleware(['auth'])
    ->name('doctor.consultation.complete');

Route::post('/doctor/consultation/{patient_id}/prescription', [ConsultationController::class, 'issuePrescription'])
    ->middleware(['auth'])
    ->name('doctor.consultation.prescription');
