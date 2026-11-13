<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SuggestionController;
use Illuminate\Support\Facades\Route;

// The phone app (guidelines §6): Sanctum tokens created in settings, everything user-scoped.
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::get('user', [CatalogueController::class, 'user']);
    Route::get('categories', [CatalogueController::class, 'categories']);
    Route::get('objects', [CatalogueController::class, 'objects']);
    Route::get('objects/{object}', [CatalogueController::class, 'object']);
    Route::patch('objects/{object}', [CatalogueController::class, 'updateObject']);
    Route::get('series', [CatalogueController::class, 'series']);
    Route::get('series/{series}', [CatalogueController::class, 'showSeries']);
    Route::post('series/{series}/sync', [CatalogueController::class, 'syncSeries'])->middleware('throttle:10,1');
    Route::get('search', SearchController::class)->middleware('throttle:catalogue-search');
    Route::post('wishlist', [SearchController::class, 'storeWishlist']);

    Route::controller(SuggestionController::class)->prefix('suggestions')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store')->middleware('throttle:10,1');
        Route::get('board', 'board');
        Route::get('{suggestion}', 'show')->whereNumber('suggestion');
        Route::post('{suggestion}/vote', 'vote')->middleware('throttle:60,1');
        Route::delete('{suggestion}/vote', 'unvote')->middleware('throttle:60,1');
        Route::post('{suggestion}/comments', 'storeComment')->middleware('throttle:20,1');
        Route::delete('{suggestion}/comments/{comment}', 'destroyComment')->scopeBindings();
    });
});
