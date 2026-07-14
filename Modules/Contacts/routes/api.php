<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\Api\V1\ContactAnnotationController;
use Modules\Contacts\Http\Controllers\Api\V1\ContactController;
use Modules\Contacts\Http\Controllers\Api\V1\ContactGraphController;
use Modules\Contacts\Http\Controllers\Api\V1\ExpandPreviewController;
use Modules\Contacts\Http\Controllers\FlickrContactController;

Route::prefix('v1')->group(function (): void {
    Route::prefix('flickr/accounts/{connection}')->group(function (): void {
        // Inertia contact list/show remain on the module web controller until a JSON contacts index exists.
        Route::get('/contacts', [FlickrContactController::class, 'index']);
        Route::get('/contacts/progress', [ContactController::class, 'progress']);
        Route::get('/contacts/suggest', [ContactController::class, 'suggest']);
        Route::post('/contacts', [ContactController::class, 'import']);
        Route::get('/contact-graph', [ContactGraphController::class, 'show']);
        Route::get('/contact-graph/delta', [ContactGraphController::class, 'delta']);
        Route::post('/contact-graph/expansions', [ContactGraphController::class, 'expand']);
        Route::get('/contacts/{contactNsid}', [FlickrContactController::class, 'show']);
        Route::post('/contacts/{contactNsid}/crawl-runs', [ContactController::class, 'storeCrawlRun']);
        Route::patch('/contacts/{contactNsid}/annotation', [ContactAnnotationController::class, 'update']);
        Route::get('/expand-previews', [ExpandPreviewController::class, 'show']);
    });
});
