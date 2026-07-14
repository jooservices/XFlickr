<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

use JOOservices\Flickr\Contracts\Client\FlickrClientContract;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\Services\RawApiService;
use Modules\Crawler\Client\ForceAuthenticatedFlickrClient;
use ReflectionProperty;
use RuntimeException;

/**
 * Ensures connection-backed Flickr clients always OAuth-sign REST calls.
 */
final class ForceAuthenticatedFlickr
{
    public static function wrap(Flickr $flickr): Flickr
    {
        $raw = $flickr->raw();
        if (! $raw instanceof RawApiService) {
            throw new RuntimeException('Unexpected Flickr raw API service; cannot force authentication.');
        }

        $property = new ReflectionProperty(RawApiService::class, 'client');
        $inner = $property->getValue($raw);
        if (! $inner instanceof FlickrClientContract) {
            throw new RuntimeException('Unexpected Flickr client; cannot force authentication.');
        }

        if ($inner instanceof ForceAuthenticatedFlickrClient) {
            return $flickr;
        }

        $property->setValue($raw, new ForceAuthenticatedFlickrClient($inner));

        return $flickr;
    }
}
