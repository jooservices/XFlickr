<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\Api\V1\StoredFileController;
use Modules\Transfer\Http\Controllers\Api\V1\TransferProgressController;

Route::prefix('v1')->group(function (): void {
    Route::get('/stored-files/{uuid}', [StoredFileController::class, 'show']);

    Route::prefix('flickr/accounts/{connection}/transfers')->group(function (): void {
        Route::get('/', [TransferProgressController::class, 'index']);
        Route::get('/{batch}', [TransferProgressController::class, 'show']);
        Route::post('/{batch}/items/{flickrPhotoId}/retries', [TransferProgressController::class, 'retryItem']);
    });
});
