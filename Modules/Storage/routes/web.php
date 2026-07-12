<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Storage\Http\Controllers\StorageAuthController;
use Modules\Storage\Http\Controllers\StorageBrowseController;

Route::middleware('auth')->group(function (): void {
    Route::get('/storage/oauth/{provider}', [StorageAuthController::class, 'connect'])->name('storage.oauth');
    Route::get('/storage/reauthorize/{account}', [StorageAuthController::class, 'reauthorize'])->name('storage.reauthorize');
    Route::get('/storage/callback/{provider}', [StorageAuthController::class, 'callback'])->name('storage.callback');
    Route::post('/storage/disconnect', [StorageAuthController::class, 'disconnect'])->name('storage.disconnect');
    Route::post('/storage/set-default', [StorageAuthController::class, 'setDefault'])->name('storage.set-default');
    Route::post('/storage/connect/r2', [StorageAuthController::class, 'connectR2'])->name('storage.connect.r2');

    Route::get('/storages/google-photos', [StorageBrowseController::class, 'googlePhotos'])->name('storages.google-photos');
    Route::get('/storages/google-drive', [StorageBrowseController::class, 'googleDrive'])->name('storages.google-drive');
    Route::get('/storages/onedrive', [StorageBrowseController::class, 'oneDrive'])->name('storages.onedrive');
    Route::get('/storages/r2', [StorageBrowseController::class, 'r2'])->name('storages.r2');
});
