<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\ConnectionPublicIdService;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionPublicIdService::class);
    }

    public function boot(): void
    {
        Connection::created(function (Connection $connection): void {
            app(ConnectionPublicIdService::class)->ensure($connection);
        });

        Route::bind('connection', function (string $value): Connection {
            if (Str::isUuid($value)) {
                return Connection::query()
                    ->where('public_id', $value)
                    ->firstOrFail();
            }

            $connection = Connection::query()
                ->where('connection_key', urldecode($value))
                ->firstOrFail();

            app(ConnectionPublicIdService::class)->ensure($connection);

            return $connection;
        });
    }
}
