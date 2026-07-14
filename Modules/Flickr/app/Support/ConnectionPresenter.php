<?php

declare(strict_types=1);

namespace Modules\Flickr\Support;

use Modules\Crawler\Models\Connection;

final class ConnectionPresenter
{
    /**
     * @return array{
     *     public_id: string,
     *     nsid: string,
     *     username: string|null,
     *     fullname: string|null,
     *     app_profile: string|null,
     *     connected_at: string|null,
     *     is_active: bool,
     *     disconnected_at: string|null
     * }
     */
    public static function toArray(Connection $connection): array
    {
        $publicId = app(ConnectionPublicIdService::class)->ensure($connection);

        return [
            'public_id' => $publicId,
            'nsid' => $connection->connection_key,
            'username' => $connection->username,
            'fullname' => $connection->fullname,
            'app_profile' => $connection->app_profile,
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'is_active' => (bool) $connection->is_active,
            'disconnected_at' => $connection->disconnected_at?->toIso8601String(),
        ];
    }
}
