<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\SearchController;
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
    Route::get('search', SearchController::class)->middleware('throttle:catalogue-search');
    Route::post('wishlist', [SearchController::class, 'storeWishlist']);
});
