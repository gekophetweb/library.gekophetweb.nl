<?php

use App\Http\Controllers\Admin\SsoProviderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\CollectionObjectController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WelcomeNameController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login/dev', DevLoginController::class)->name('login.dev');

    Route::post('login/code', [EmailCodeController::class, 'request'])->name('login.code.request');
    Route::get('login/code', [EmailCodeController::class, 'show'])->name('login.code');
    Route::post('login/code/verify', [EmailCodeController::class, 'verify'])
        ->middleware('throttle:20,1')
        ->name('login.code.verify');
    Route::get('login/link/{loginCode}', [EmailCodeController::class, 'link'])
        ->middleware(['signed', 'throttle:20,1'])
        ->name('login.code.link');
});

// Guests log in here; signed-in users link a provider; admins test one.
Route::get('auth/sso/{provider}', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('auth/sso/{provider}/callback', [SsoController::class, 'callback'])->name('sso.callback');

Route::middleware('auth')->group(function () {
    Route::get('/', [LibraryController::class, 'dashboard'])->name('home');
    Route::get('library/categories/{category}', [LibraryController::class, 'category'])->name('library.category');
    Route::get('library/series/{series}', [LibraryController::class, 'series'])->name('library.series');
    Route::get('objects/{object}', [CollectionObjectController::class, 'show'])->name('objects.show');
    Route::put('objects/{object}', [CollectionObjectController::class, 'update'])->name('objects.update');

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('welcome/name', [WelcomeNameController::class, 'show'])->name('welcome.name');
    Route::post('welcome/name', [WelcomeNameController::class, 'store'])->name('welcome.name.store');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/api-keys/{provider}', [SettingsController::class, 'updateApiKey'])->name('settings.api-keys.update');
    Route::delete('settings/api-keys/{provider}', [SettingsController::class, 'destroyApiKey'])->name('settings.api-keys.destroy');
    Route::delete('settings/identities/{identity}', [SettingsController::class, 'destroyIdentity'])->name('settings.identities.destroy');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');

        Route::put('sso/settings', [SsoProviderController::class, 'updateSettings'])->name('sso.settings');
        Route::get('sso/{provider}/test', [SsoController::class, 'test'])->name('sso.test');
        Route::resource('sso', SsoProviderController::class)
            ->parameters(['sso' => 'provider'])
            ->except('show');
    });
});
