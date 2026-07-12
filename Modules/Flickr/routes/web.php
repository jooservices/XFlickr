<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Flickr\Http\Controllers\FlickrAccountController;
use Modules\Flickr\Http\Controllers\FlickrAuthController;

Route::middleware('auth')->group(function (): void {
    Route::get('/flickr/oauth', [FlickrAuthController::class, 'connect'])->name('flickr.connect');
    Route::get('/flickr/callback', [FlickrAuthController::class, 'callback'])->name('flickr.callback');
    Route::post('/flickr/disconnect', [FlickrAuthController::class, 'disconnect'])->name('flickr.disconnect');
    Route::post('/flickr/activate', [FlickrAuthController::class, 'activate'])->name('flickr.activate');

    Route::get('/flickr/accounts', [FlickrAccountController::class, 'list'])->name('flickr.accounts.index');
    Route::get('/flickr/accounts/{connection}', [FlickrAccountController::class, 'show'])->name('flickr.accounts.show');
    Route::post('/flickr/accounts/{connection}/crawl', [FlickrAccountController::class, 'crawl'])->name('flickr.accounts.crawl');
});
