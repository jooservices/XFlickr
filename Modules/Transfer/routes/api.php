<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\Api\V1\IntegrityController;
use Modules\Transfer\Http\Controllers\Api\V1\StoredFileController;
use Modules\Transfer\Http\Controllers\Api\V1\TransferProgressController;

Route::prefix('v1')->group(function (): void {
    Route::get('/stored-files/{uuid}', [StoredFileController::class, 'show']);

    Route::prefix('transfers/integrity-scans')->group(function (): void {
        Route::post('/', [IntegrityController::class, 'store']);
        Route::get('/{scan:uuid}', [IntegrityController::class, 'show']);
        Route::get('/{scan:uuid}/anomalies', [IntegrityController::class, 'anomalies']);
        Route::post('/{scan:uuid}/resolutions', [IntegrityController::class, 'resolve']);
    });

    Route::prefix('flickr/accounts/{connection}/transfers')->group(function (): void {
        Route::get('/', [TransferProgressController::class, 'index']);
        Route::get('/items', [TransferProgressController::class, 'itemIndex']);
        Route::get('/{batch}', [TransferProgressController::class, 'show']);
        Route::post('/{batch}/items/{flickrPhotoId}/retries', [TransferProgressController::class, 'retryItem']);
        Route::post('/{batch}/retries', [TransferProgressController::class, 'retryBatch']);
    });
});
