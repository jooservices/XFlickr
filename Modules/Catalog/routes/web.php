<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CatalogController;

Route::middleware('auth')->group(function (): void {
    Route::get('/flickr/accounts/{connection}/photos', [CatalogController::class, 'photos'])->name('flickr.accounts.photos');
    Route::get('/flickr/accounts/{connection}/photosets', [CatalogController::class, 'photosets'])->name('flickr.accounts.photosets');
    Route::get('/flickr/accounts/{connection}/photosets/{photoset}', [CatalogController::class, 'showPhotoset'])->name('flickr.accounts.photosets.show');
    Route::get('/flickr/accounts/{connection}/galleries', [CatalogController::class, 'galleries'])->name('flickr.accounts.galleries');

    Route::get('/photos', [CatalogController::class, 'photos'])->name('photos.index');
    Route::get('/photosets', [CatalogController::class, 'photosets'])->name('photosets.index');
    Route::get('/photosets/{photoset}', [CatalogController::class, 'showPhotoset'])->name('photosets.show');
    Route::get('/galleries', [CatalogController::class, 'galleries'])->name('galleries.index');
    Route::get('/favorites', [CatalogController::class, 'favorites'])->name('favorites.index');
});
