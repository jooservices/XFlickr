<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\ConnectionsController;
use Modules\Settings\Http\Controllers\FlickrAppProfileController;
use Modules\Settings\Http\Controllers\RuntimeConfigController;
use Modules\Settings\Http\Controllers\SettingsController;
use Modules\Settings\Http\Controllers\StorageAppProfileController;

Route::middleware('auth')->group(function (): void {
    Route::get('/connections', [ConnectionsController::class, 'index'])->name('connections.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/config', [RuntimeConfigController::class, 'store'])->name('settings.config.store');
    Route::post('/settings/crawl-pause', [RuntimeConfigController::class, 'updateCrawlPause'])->name('settings.crawl-pause.update');
    Route::post('/settings/spider', [RuntimeConfigController::class, 'updateSpiderMode'])->name('settings.spider.update');
    Route::delete('/settings/config/{path}', [RuntimeConfigController::class, 'destroy'])->name('settings.config.destroy');
    Route::post('/settings/config/{path}/reset', [RuntimeConfigController::class, 'reset'])->name('settings.config.reset');
    Route::post('/settings/flickr-app', [FlickrAppProfileController::class, 'store'])->name('settings.flickr-app.store');
    Route::delete('/settings/flickr-app/{profile}', [FlickrAppProfileController::class, 'destroy'])->name('settings.flickr-app.destroy');
    Route::post('/settings/storage-app', [StorageAppProfileController::class, 'store'])->name('settings.storage-app.store');
});
