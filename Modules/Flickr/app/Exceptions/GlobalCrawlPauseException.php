<?php

declare(strict_types=1);

namespace Modules\Flickr\Exceptions;

use RuntimeException;

final class GlobalCrawlPauseException extends RuntimeException
{
    public static function active(): self
    {
        return new self(
            'Global crawl pause is active. Resume from the header to start crawls.',
        );
    }
}
