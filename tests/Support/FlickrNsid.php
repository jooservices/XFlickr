<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Flickr NSID-shaped identifiers for tests (e.g. 12037949629@N01).
 */
final class FlickrNsid
{
    public static function fake(): string
    {
        return fake()->unique()->numerify('##########@N##');
    }
}
