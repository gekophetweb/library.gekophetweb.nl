<?php

use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\WelcomeNameController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');

    Route::post('login/code', [EmailCodeController::class, 'request'])->name('login.code.request');
    Route::get('login/code', [EmailCodeController::class, 'show'])->name('login.code');
    Route::post('login/code/verify', [EmailCodeController::class, 'verify'])
        ->middleware('throttle:20,1')
        ->name('login.code.verify');
    Route::get('login/link/{loginCode}', [EmailCodeController::class, 'link'])
        ->middleware(['signed', 'throttle:20,1'])
        ->name('login.code.link');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'home')->name('home');

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('welcome/name', [WelcomeNameController::class, 'show'])->name('welcome.name');
    Route::post('welcome/name', [WelcomeNameController::class, 'store'])->name('welcome.name.store');
});
