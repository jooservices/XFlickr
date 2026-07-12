<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\ContactFullPassController;
use Modules\Contacts\Http\Controllers\ContactsController;
use Modules\Contacts\Http\Controllers\FlickrContactController;

Route::middleware('auth')->group(function (): void {
    Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');

    Route::get('/flickr/accounts/{connection}/contacts', [FlickrContactController::class, 'index'])->name('flickr.accounts.contacts.index');
    Route::post('/flickr/accounts/{connection}/contacts/crawl', [FlickrContactController::class, 'crawlBulk'])->name('flickr.accounts.contacts.crawl-bulk');
    Route::get('/flickr/accounts/{connection}/contacts/{contactNsid}', [FlickrContactController::class, 'show'])->name('flickr.accounts.contacts.show');
    Route::post('/flickr/accounts/{connection}/contacts/{contactNsid}/crawl', [FlickrContactController::class, 'crawl'])->name('flickr.accounts.contacts.crawl');

    Route::post('/flickr/accounts/{connection}/full-pass/start', [ContactFullPassController::class, 'start'])->name('flickr.full-pass.start');
    Route::post('/flickr/accounts/{connection}/full-pass/stop', [ContactFullPassController::class, 'stop'])->name('flickr.full-pass.stop');
});
