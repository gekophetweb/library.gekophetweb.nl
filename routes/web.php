<?php

use App\Http\Controllers\Admin\SsoProviderController;
use App\Http\Controllers\Admin\SuggestionController as AdminSuggestionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\EmailCodeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\CollectionObjectController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ScanBatchController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\LibraryShareController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Share\SharedLibraryController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\WelcomeNameController;
use App\Http\Controllers\WishlistController;
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

// Read-only share links (plan share-library-read-only): GET only, no account needed, and
// nothing here reads the signed-in user. Must stay reachable without Cloudflare Access.
Route::middleware(['share', 'throttle:60,1'])
    ->prefix('s/{shareToken}')->name('share.')
    ->controller(SharedLibraryController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('categories/{category}', 'category')->name('category');
        Route::get('series/{series}', 'series')->name('series');
        Route::get('objects/{object}', 'object')->name('object');
    });

// Guests log in here; signed-in users link a provider; admins test one.
Route::get('auth/sso/{provider}', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('auth/sso/{provider}/callback', [SsoController::class, 'callback'])->name('sso.callback');

Route::middleware('auth')->group(function () {
    Route::get('/', [LibraryController::class, 'dashboard'])->name('home');
    Route::get('library/categories/{category}', [LibraryController::class, 'category'])->name('library.category');
    Route::get('library/series/{series}', [LibraryController::class, 'series'])->name('library.series');
    Route::post('library/series/{series}/sync', [LibraryController::class, 'syncSeries'])->middleware('throttle:10,1')->name('library.series.sync');
    Route::post('library/series/{series}/wishlist', [LibraryController::class, 'wishMissing'])->name('library.series.wish-missing');
    Route::post('library/series/{series}/volumes/{volume}/wishlist', [LibraryController::class, 'wishMissing'])->scopeBindings()->name('library.series.volumes.wish');
    Route::get('library/artists/{artist}', [LibraryController::class, 'artist'])->name('library.artist');
    Route::get('search', [SearchController::class, 'index'])->middleware('throttle:catalogue-search')->name('search.index');
    Route::post('wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::get('objects/{object}', [CollectionObjectController::class, 'show'])->name('objects.show');
    Route::put('objects/{object}', [CollectionObjectController::class, 'update'])->name('objects.update');
    Route::patch('objects/{object}/ownership', [CollectionObjectController::class, 'updateOwnership'])->name('objects.ownership');
    Route::post('objects/{object}/series-suggestion', [CollectionObjectController::class, 'acceptSeriesSuggestion'])->name('objects.series-suggestion.accept');
    Route::delete('objects/{object}/series-suggestion', [CollectionObjectController::class, 'dismissSeriesSuggestion'])->name('objects.series-suggestion.dismiss');

    Route::post('scan', [ScanController::class, 'start'])->name('scan.start');
    Route::get('scan/single', [ScanController::class, 'single'])->name('scan.single');
    Route::post('scan/single/lookup', [ScanController::class, 'lookup'])->middleware('throttle:60,1')->name('scan.single.lookup');
    Route::post('scan/single/add', [ScanController::class, 'add'])->name('scan.single.add');
    Route::get('scan/batches/{batch}', [ScanBatchController::class, 'show'])->name('scan.batches.show');
    Route::post('scan/batches/{batch}/items', [ScanBatchController::class, 'storeItem'])->name('scan.batches.items.store');
    Route::delete('scan/batches/{batch}/items/{item}', [ScanBatchController::class, 'destroyItem'])->name('scan.batches.items.destroy');
    Route::post('scan/batches/{batch}/commit', [ScanBatchController::class, 'commit'])->name('scan.batches.commit');

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('welcome/name', [WelcomeNameController::class, 'show'])->name('welcome.name');
    Route::post('welcome/name', [WelcomeNameController::class, 'store'])->name('welcome.name.store');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/api-keys/{provider}', [SettingsController::class, 'updateApiKey'])->name('settings.api-keys.update');
    Route::delete('settings/api-keys/{provider}', [SettingsController::class, 'destroyApiKey'])->name('settings.api-keys.destroy');
    Route::post('settings/tokens', [SettingsController::class, 'storeToken'])->name('settings.tokens.store');
    Route::delete('settings/tokens/{token}', [SettingsController::class, 'destroyToken'])->whereNumber('token')->name('settings.tokens.destroy');
    Route::delete('settings/identities/{identity}', [SettingsController::class, 'destroyIdentity'])->name('settings.identities.destroy');
    Route::post('settings/shares', [LibraryShareController::class, 'store'])->name('settings.shares.store');
    Route::delete('settings/shares/{share}', [LibraryShareController::class, 'destroy'])->name('settings.shares.destroy');

    Route::get('suggestions', [SuggestionController::class, 'index'])->name('suggestions.index');
    Route::post('suggestions', [SuggestionController::class, 'store'])->middleware('throttle:10,1')->name('suggestions.store');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::get('suggestions', [AdminSuggestionController::class, 'index'])->name('suggestions.index');
        Route::patch('suggestions/{suggestion}', [AdminSuggestionController::class, 'update'])->name('suggestions.update');

        Route::put('sso/settings', [SsoProviderController::class, 'updateSettings'])->name('sso.settings');
        Route::get('sso/{provider}/test', [SsoController::class, 'test'])->name('sso.test');
        Route::resource('sso', SsoProviderController::class)
            ->parameters(['sso' => 'provider'])
            ->except('show');
    });
});
