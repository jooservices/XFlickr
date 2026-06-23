<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\CrawlOperationsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlickrAccountController;
use App\Http\Controllers\FlickrAppProfileController;
use App\Http\Controllers\FlickrAuthController;
use App\Http\Controllers\FlickrContactController;
use App\Http\Controllers\PhotoDownloadController;
use App\Http\Controllers\PhotoUploadController;
use App\Http\Controllers\RuntimeConfigController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StorageAppProfileController;
use App\Http\Controllers\StorageAuthController;
use App\Http\Controllers\StorageBrowseController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/config', [RuntimeConfigController::class, 'store'])->name('settings.config.store');
Route::delete('/settings/config/{path}', [RuntimeConfigController::class, 'destroy'])->name('settings.config.destroy');
Route::post('/settings/config/{path}/reset', [RuntimeConfigController::class, 'reset'])->name('settings.config.reset');
Route::post('/settings/flickr-app', [FlickrAppProfileController::class, 'store'])->name('settings.flickr-app.store');
Route::post('/settings/storage-app', [StorageAppProfileController::class, 'store'])->name('settings.storage-app.store');

Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');

Route::get('/flickr/oauth', [FlickrAuthController::class, 'connect'])->name('flickr.connect');
Route::get('/flickr/callback', [FlickrAuthController::class, 'callback'])->name('flickr.callback');
Route::post('/flickr/disconnect', [FlickrAuthController::class, 'disconnect'])->name('flickr.disconnect');
Route::post('/flickr/activate', [FlickrAuthController::class, 'activate'])->name('flickr.activate');

Route::get('/storage/oauth/{provider}', [StorageAuthController::class, 'connect'])->name('storage.oauth');
Route::get('/storage/reauthorize/{account}', [StorageAuthController::class, 'reauthorize'])->name('storage.reauthorize');
Route::get('/storage/callback/{provider}', [StorageAuthController::class, 'callback'])->name('storage.callback');
Route::post('/storage/disconnect', [StorageAuthController::class, 'disconnect'])->name('storage.disconnect');
Route::post('/storage/set-default', [StorageAuthController::class, 'setDefault'])->name('storage.set-default');
Route::post('/storage/connect/r2', [StorageAuthController::class, 'connectR2'])->name('storage.connect.r2');

Route::get('/flickr/accounts', [FlickrAccountController::class, 'list'])->name('flickr.accounts.index');
Route::get('/flickr/accounts/{connection}', [FlickrAccountController::class, 'index'])->name('flickr.accounts.show');
Route::post('/flickr/accounts/{connection}/crawl', [FlickrAccountController::class, 'crawl'])->name('flickr.accounts.crawl');
Route::post('/flickr/accounts/{connection}/download', [PhotoDownloadController::class, 'store'])->name('flickr.accounts.download');
Route::post('/flickr/accounts/{connection}/upload', [PhotoUploadController::class, 'store'])->name('flickr.accounts.upload');

Route::get('/flickr/accounts/{connection}/contacts', [FlickrContactController::class, 'index'])->name('flickr.accounts.contacts.index');
Route::post('/flickr/accounts/{connection}/contacts/crawl', [FlickrContactController::class, 'crawlBulk'])->name('flickr.accounts.contacts.crawl-bulk');
Route::get('/flickr/accounts/{connection}/contacts/{contactNsid}', [FlickrContactController::class, 'show'])->name('flickr.accounts.contacts.show');
Route::post('/flickr/accounts/{connection}/contacts/{contactNsid}/crawl', [FlickrContactController::class, 'crawl'])->name('flickr.accounts.contacts.crawl');

Route::get('/flickr/accounts/{connection}/photos', [CatalogController::class, 'photos'])->name('flickr.accounts.photos');
Route::get('/flickr/accounts/{connection}/photosets', [CatalogController::class, 'photosets'])->name('flickr.accounts.photosets');
Route::get('/flickr/accounts/{connection}/galleries', [CatalogController::class, 'galleries'])->name('flickr.accounts.galleries');

Route::get('/crawl/operations', [CrawlOperationsController::class, 'index'])->name('crawl.operations');

Route::get('/photos', [CatalogController::class, 'photos'])->name('photos.index');
Route::get('/photosets', [CatalogController::class, 'photosets'])->name('photosets.index');
Route::get('/galleries', [CatalogController::class, 'galleries'])->name('galleries.index');
Route::get('/favorites', [CatalogController::class, 'favorites'])->name('favorites.index');

Route::get('/storages/google-photos', [StorageBrowseController::class, 'googlePhotos'])->name('storages.google-photos');
Route::get('/storages/google-drive', [StorageBrowseController::class, 'googleDrive'])->name('storages.google-drive');
Route::get('/storages/onedrive', [StorageBrowseController::class, 'oneDrive'])->name('storages.onedrive');
Route::get('/storages/r2', [StorageBrowseController::class, 'r2'])->name('storages.r2');
