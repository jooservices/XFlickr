<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Support;

use Modules\Crawler\DTO\FlickrPermit;
use Modules\Crawler\Services\FlickrRequestLimiter;

final class StubFlickrPermitAcquirer
{
    public function __construct(
        private readonly FlickrPermit $permit,
    ) {}

    public function acquire(FlickrRequestLimiter $limiter, string $connectionKey, int $maxAttempts = 120): FlickrPermit
    {
        return $this->permit;
    }
}
