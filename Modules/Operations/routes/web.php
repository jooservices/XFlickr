<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\CrawlOperationsController;
use Modules\Operations\Http\Controllers\DashboardController;

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/crawl/operations', [CrawlOperationsController::class, 'index'])->name('crawl.operations');
});
