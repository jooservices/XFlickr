<?php

declare(strict_types=1);

namespace Modules\Crawler\Exceptions;

use RuntimeException;

final class FlickrAppNotConfiguredException extends RuntimeException
{
    public static function forProfile(string $appProfile): self
    {
        return new self(
            "Flickr app profile [{$appProfile}] is not configured in laravel-config "
            ."(xflickr_app.{$appProfile})."
        );
    }

    public static function invalidProfile(string $appProfile): self
    {
        return new self("Flickr app profile [{$appProfile}] is invalid.");
    }
}
