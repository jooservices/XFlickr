<?php

declare(strict_types=1);

namespace Modules\Transfer\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Transfer';

    public function map(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('api')
            ->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
