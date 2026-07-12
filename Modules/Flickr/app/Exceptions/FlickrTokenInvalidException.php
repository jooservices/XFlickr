<?php

declare(strict_types=1);

namespace Modules\Flickr\Exceptions;

use JOOservices\XFlickrCrawler\Models\Connection;
use RuntimeException;

final class FlickrTokenInvalidException extends RuntimeException
{
    public static function forConnection(Connection $connection): self
    {
        $label = $connection->username ?? $connection->connection_key;

        return new self(
            "Flickr account [{$label}] token is invalid. Reconnect the account in Settings → Flickr.",
        );
    }
}
