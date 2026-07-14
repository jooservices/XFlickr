<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Modules\Crawler\DTO\FlickrPermit;

final class FlickrPermitAcquirer
{
    public function acquire(FlickrRequestLimiter $limiter, string $connectionKey, int $maxAttempts = 120): FlickrPermit
    {
        return $limiter->acquire($connectionKey);
    }
}
