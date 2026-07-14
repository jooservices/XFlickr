<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Tests\TestCase;

final class FlickrCrawlerManagerTest extends TestCase
{
    public function test_limiter_state_returns_expected_keys(): void
    {
        $this->requiresRedis();

        $connectionKey = 'limiter-key-'.uniqid();
        $this->cleanLimiterKeys($connectionKey);

        $state = FlickrService::limiterState($connectionKey);

        $this->assertArrayHasKey('max_requests_per_hour', $state);
        $this->assertArrayHasKey('requests_used', $state);
        $this->assertArrayHasKey('requests_remaining', $state);
    }
}
