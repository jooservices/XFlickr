<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CrawlStatusController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StorageBrowseController;
use App\Http\Controllers\Api\TransferProgressController;
use App\Http\Controllers\FlickrAccountController;
use App\Http\Controllers\FlickrContactController;
use App\Http\Controllers\PhotoDownloadController;
use App\Http\Controllers\PhotoUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot']);

Route::prefix('flickr/accounts/{connection}')->group(function (): void {
    Route::get('/', [FlickrAccountController::class, 'index']);
    Route::post('/crawl', [FlickrAccountController::class, 'crawl']);
    Route::post('/download', [PhotoDownloadController::class, 'store']);
    Route::post('/upload', [PhotoUploadController::class, 'store']);

    Route::get('/contacts', [FlickrContactController::class, 'index']);
    Route::get('/contacts/progress', [FlickrContactController::class, 'progress']);
    Route::get('/contacts/suggest', [FlickrContactController::class, 'suggest']);
    Route::get('/contacts/{contactNsid}', [FlickrContactController::class, 'show']);
    Route::post('/contacts/{contactNsid}/crawl', [FlickrContactController::class, 'crawl']);

    Route::get('/transfers', [TransferProgressController::class, 'index']);
    Route::get('/transfers/{batch}', [TransferProgressController::class, 'show']);

    Route::get('/crawl/summary', [CrawlStatusController::class, 'summary']);
    Route::get('/crawl/runs', [CrawlStatusController::class, 'runs']);
    Route::get('/crawl/logs', [CrawlStatusController::class, 'logs']);
});

Route::prefix('flickr/catalog')->group(function (): void {
    Route::get('/photos', [CatalogController::class, 'photos']);
    Route::get('/photosets', [CatalogController::class, 'photosets']);
    Route::get('/galleries', [CatalogController::class, 'galleries']);
    Route::get('/favorites', [CatalogController::class, 'favorites']);
});

Route::prefix('storage')->group(function (): void {
    Route::get('/accounts', [StorageBrowseController::class, 'accounts']);
    Route::get('/google-photos/thumbnail', [StorageBrowseController::class, 'googlePhotosThumbnail']);
    Route::get('/{provider}/download', [StorageBrowseController::class, 'download']);
    Route::get('/{provider}/browse', [StorageBrowseController::class, 'browse']);
    Route::post('/{provider}/sync', [StorageBrowseController::class, 'sync']);
    Route::post('/{provider}/delete', [StorageBrowseController::class, 'delete']);
});
