<?php

use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitation/{token}', [InvitationController::class, 'show'])
    ->middleware('guest')
    ->name('invitation.show');

Route::post('/invitation/{token}', [InvitationController::class, 'activate'])
    ->middleware('guest')
    ->name('invitation.activate');
