<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Modules\Crawler\Models\Connection;

final class ConnectionQueryRepository
{
    public function findByConnectionKey(string $connectionKey): ?Connection
    {
        return Connection::query()
            ->byConnectionKey($connectionKey)
            ->first();
    }

    public function findByConnectionKeyOrFail(string $connectionKey): Connection
    {
        return Connection::query()
            ->byConnectionKey($connectionKey)
            ->firstOrFail();
    }

    public function countByAppProfile(string $profile): int
    {
        return Connection::query()
            ->where('app_profile', $profile)
            ->count();
    }
}
