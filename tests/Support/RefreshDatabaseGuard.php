<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Blocks migrate:fresh from Feature tests when pointed at Docker dev in-container MySQL.
 *
 * PHPUnit in Docker test stack (sqlite :memory:) passes.
 * docker exec xflickr-dev-app-1 php artisan test sets DB_HOST=mysql via Compose → throws.
 */
final class RefreshDatabaseGuard
{
    public static function assertSafeTarget(): void
    {
        if (config('app.env') !== 'testing') {
            throw new \RuntimeException(
                'Refusing RefreshDatabase when APP_ENV is not "testing". '
                .'Run tests via: bash scripts/test.sh gate:test'
            );
        }

        $defaultConnection = (string) config('database.default');

        if ($defaultConnection === 'sqlite') {
            return;
        }

        $host = (string) config("database.connections.{$defaultConnection}.host", '');

        if ($host === 'mysql') {
            throw new \RuntimeException(
                'Refusing RefreshDatabase against Docker in-container MySQL (DB_HOST=mysql). '
                .'Never run: docker exec xflickr-dev-app-1 php artisan test. '
                .'Use: bash scripts/test.sh gate:test (isolated test stack).'
            );
        }
    }
}
