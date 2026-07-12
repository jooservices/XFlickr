<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\PhotoDownloadController;
use Modules\Transfer\Http\Controllers\PhotoUploadController;

Route::middleware('auth')->group(function (): void {
    Route::post('/flickr/accounts/{connection}/download', [PhotoDownloadController::class, 'store'])->name('flickr.accounts.download');
    Route::post('/flickr/accounts/{connection}/upload', [PhotoUploadController::class, 'store'])->name('flickr.accounts.upload');
});
