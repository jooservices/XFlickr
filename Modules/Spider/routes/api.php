<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Spider\Http\Controllers\Api\V1\SpiderStatusController;

Route::prefix('v1')->group(function (): void {
    Route::get('/flickr/accounts/{connection}/spider-runs/current', [SpiderStatusController::class, 'show']);
});
