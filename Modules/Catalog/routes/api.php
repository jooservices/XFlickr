<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CatalogController;

Route::prefix('v1')->group(function (): void {
    Route::prefix('flickr/catalog')->group(function (): void {
        Route::get('/photos', [CatalogController::class, 'photos']);
        Route::get('/photosets', [CatalogController::class, 'photosets']);
        Route::get('/photosets/{photoset}', [CatalogController::class, 'showPhotoset']);
        Route::get('/galleries', [CatalogController::class, 'galleries']);
        Route::get('/favorites', [CatalogController::class, 'favorites']);
    });
});
