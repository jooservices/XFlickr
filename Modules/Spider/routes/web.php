<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Spider\Http\Controllers\SpiderController;

Route::middleware('auth')->group(function (): void {
    Route::post('/flickr/accounts/{connection}/spider/start', [SpiderController::class, 'start'])->name('flickr.spider.start');
    Route::post('/flickr/accounts/{connection}/spider/stop', [SpiderController::class, 'stop'])->name('flickr.spider.stop');
});
