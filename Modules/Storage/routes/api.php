<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Storage\Http\Controllers\Api\V1\StorageBrowseController;
use Modules\Storage\Http\Controllers\Api\V1\StorageQuotaController;
use Modules\Storage\Http\Controllers\Api\V1\StoredFileController;

Route::prefix('v1')->group(function (): void {
    Route::get('/stored-files/{uuid}', [StoredFileController::class, 'show']);

    Route::prefix('storage')->group(function (): void {
        Route::get('/accounts', [StorageBrowseController::class, 'accounts']);
        Route::get('/quota', [StorageQuotaController::class, 'index']);
        Route::get('/google-photos/thumbnail', [StorageBrowseController::class, 'googlePhotosThumbnail']);
        Route::get('/{provider}/files/download', [StorageBrowseController::class, 'download']);
        Route::get('/{provider}/files', [StorageBrowseController::class, 'browse']);
        Route::post('/{provider}/sync-runs', [StorageBrowseController::class, 'sync']);
        Route::delete('/{provider}/files', [StorageBrowseController::class, 'delete']);
    });
});
