<?php

use App\Http\Controllers\Patient\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('patient')->name('patient.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'createLogin'])->name('login');
        Route::post('login', [AuthController::class, 'storeLogin']);
        Route::get('register', [AuthController::class, 'createRegister'])->name('register');
        Route::post('register', [AuthController::class, 'storeRegister']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('dashboard', function () {
            return view('patient.dashboard');
        })->name('dashboard');
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
    });
});
