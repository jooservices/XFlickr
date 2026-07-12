<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\Api\V1\DashboardController;
use Modules\Operations\Http\Controllers\Api\V1\OperationsSnapshotController;
use Modules\Operations\Http\Controllers\Api\V1\OperationsStreamController;

Route::prefix('v1')->group(function (): void {
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot']);
    Route::get('/operations/snapshot', [OperationsSnapshotController::class, 'show']);
    Route::get('/operations/stream', [OperationsStreamController::class, 'stream']);
});
