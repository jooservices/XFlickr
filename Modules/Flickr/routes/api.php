<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Flickr\Http\Controllers\Api\V1\CrawlStatusController;
use Modules\Flickr\Http\Controllers\Api\V1\FlickrAccountController;
use Modules\Flickr\Http\Controllers\Api\V1\FlickrRateLimitController;
use Modules\Flickr\Http\Controllers\Api\V1\FlickrRateLimitUsageController;
use Modules\Flickr\Http\Controllers\Api\V1\FlickrTokenHealthController;
use Modules\Flickr\Http\Controllers\Api\V1\TransferProgressController;

Route::prefix('v1')->group(function (): void {
    Route::get('/flickr/rate-limit', [FlickrRateLimitController::class, 'index']);
    Route::get('/flickr/rate-limit/usage', [FlickrRateLimitUsageController::class, 'show']);

    Route::prefix('flickr/accounts/{connection}')->group(function (): void {
        Route::get('/', [FlickrAccountController::class, 'show']);
        Route::post('/crawl-runs', [FlickrAccountController::class, 'storeCrawlRun']);
        Route::get('/crawl/summary', [CrawlStatusController::class, 'summary']);
        Route::get('/crawl/runs', [CrawlStatusController::class, 'runs']);
        Route::get('/crawl/logs', [CrawlStatusController::class, 'logs']);
        Route::get('/token-health', [FlickrTokenHealthController::class, 'show']);
        Route::get('/transfers', [TransferProgressController::class, 'index']);
        Route::get('/transfers/{batch}', [TransferProgressController::class, 'show']);
        Route::post('/transfers/{batch}/items/{flickrPhotoId}/retries', [TransferProgressController::class, 'retryItem']);
    });
});
