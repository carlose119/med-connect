<?php

use App\Http\Controllers\Patient\AuthController;
use App\Http\Controllers\Patient\CancelAppointmentController;
use App\Http\Controllers\Patient\ProfileController;
use App\Livewire\Patient\BookAppointment;
use App\Livewire\Patient\Dashboard;
use App\Livewire\Patient\DoctorList;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->name('patient.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('patient.login');
    });

    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'createLogin'])->name('login');
        Route::post('login', [AuthController::class, 'storeLogin']);
        Route::get('register', [AuthController::class, 'createRegister'])->name('register');
        Route::post('register', [AuthController::class, 'storeRegister']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('doctors', DoctorList::class)->name('doctors');
        Route::get('doctors/{doctor}/book', BookAppointment::class)->name('book');
        Route::post('appointments/{appointment}/cancel', CancelAppointmentController::class)->name('cancel');
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
        Route::post('profile', [ProfileController::class, 'update']);
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
    });
});
