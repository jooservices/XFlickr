<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\CrawlOperationsController;
use Modules\Operations\Http\Controllers\DashboardController;
use Modules\Operations\Http\Controllers\SyncOperationsController;

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/operations', [CrawlOperationsController::class, 'index'])->name('operations');
    Route::get('/sync', [SyncOperationsController::class, 'index'])->name('sync');
    Route::redirect('/crawl/operations', '/operations', 301);
});
