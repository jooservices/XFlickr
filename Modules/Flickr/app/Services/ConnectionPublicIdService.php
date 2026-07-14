<?php

declare(strict_types=1);

namespace Modules\Flickr\Services;

use Illuminate\Support\Str;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Repositories\ConnectionRepository;

final class ConnectionPublicIdService
{
    public function __construct(
        private readonly ConnectionRepository $connections,
    ) {}

    public function ensure(Connection $connection): string
    {
        $publicId = $connection->getAttribute('public_id');

        if (is_string($publicId) && $publicId !== '') {
            return $publicId;
        }

        $publicId = (string) Str::uuid();

        $id = $connection->getKey();
        if ($id !== null) {
            $this->connections->updateById($id, [
                'public_id' => $publicId,
            ]);
        }

        $connection->setAttribute('public_id', $publicId);

        return $publicId;
    }
}
