<?php

declare(strict_types=1);

namespace App\Support\Flickr;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JOOservices\XFlickrCrawler\Models\Connection;

final class ConnectionPublicIdService
{
    public function ensure(Connection $connection): string
    {
        $publicId = $connection->getAttribute('public_id');

        if (is_string($publicId) && $publicId !== '') {
            return $publicId;
        }

        $publicId = (string) Str::uuid();

        DB::table($connection->getTable())
            ->where('id', $connection->id)
            ->update(['public_id' => $publicId]);

        $connection->setAttribute('public_id', $publicId);

        return $publicId;
    }
}
